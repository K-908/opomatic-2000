<?php

declare(strict_types=1);

use OpoMatic2000\Infrastructure\Persistence\DatabaseConfig;
use OpoMatic2000\Infrastructure\Persistence\DatabaseConnectionException;
use OpoMatic2000\Infrastructure\Persistence\PdoConnectionFactory;
use OpoMatic2000\Infrastructure\Persistence\TopicRepository;
use OpoMatic2000\Infrastructure\Persistence\TransactionManager;
use OpoMatic2000\Infrastructure\Persistence\UserRepository;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PDOException) {
        return;
    }

    throw new RuntimeException($message);
}

$config = DatabaseConfig::fromEnvironment();
if (!preg_match('/^opomatic_p03_[a-z0-9_]+$/', $config->name)) {
    throw new RuntimeException('La prueba exige una base temporal con prefijo opomatic_p03_.');
}

try {
    new DatabaseConfig('localhost;dbname=otra', 3306, 'test', 'user', 'secret');
    throw new RuntimeException('DB_HOST con separador DSN debe rechazarse.');
} catch (InvalidArgumentException) {
}

$factory = new PdoConnectionFactory();
try {
    $factory->connect(new DatabaseConfig($config->host, $config->port, 'opomatic_p03_no_existe', $config->user, 'secreto-prueba'));
    throw new RuntimeException('La conexión inválida debe fallar.');
} catch (DatabaseConnectionException $error) {
    check(!str_contains($error->getMessage(), 'secreto-prueba'), 'El error revela la contraseña.');
    check($error->getPrevious() === null, 'El error conserva detalles privados del driver.');
}

$db = $factory->connect($config);
check($db->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION, 'PDO debe usar excepciones.');
check((bool) $db->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false, 'PDO debe usar preparación nativa.');
check($db->query('SELECT @@character_set_connection')->fetchColumn() === 'utf8mb4', 'La conexión debe usar utf8mb4.');

$expected = [
    'Users', 'LoginAccesses', 'Topics', 'Questions', 'QuestionOptions',
    'TestAttempts', 'TestAttemptTopics', 'TestAttemptQuestions', 'TestAttemptOptions',
];
$tables = $db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'')
    ->fetchAll(PDO::FETCH_COLUMN);
$expectedNames = array_map('strtolower', $expected);
$actualNames = array_map('strtolower', $tables);
sort($expectedNames);
sort($actualNames);
check($actualNames === $expectedNames, 'El esquema no contiene exactamente las nueve tablas esperadas.');

foreach ($expected as $table) {
    check((int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() === 0, "La tabla {$table} debe estar vacía.");
}

$constraints = $db->query(
    "SELECT CONSTRAINT_TYPE, COUNT(*) AS Quantity FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE IN ('FOREIGN KEY', 'CHECK')
     GROUP BY CONSTRAINT_TYPE",
)->fetchAll(PDO::FETCH_KEY_PAIR);
check((int) ($constraints['FOREIGN KEY'] ?? 0) === 10, 'Faltan claves foráneas.');
check((int) ($constraints['CHECK'] ?? 0) === 10, 'Faltan restricciones CHECK.');

$transactions = new TransactionManager($db);
$users = new UserRepository($db);
$topics = new TopicRepository($db);
$username = 'p03_' . bin2hex(random_bytes(5));
$hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

$result = $transactions->run(static function (PDO $connection) use ($users, $topics, $username, $hash): string {
    $users->create($username, $hash);
    check($users->findByUsername($username) === ['Username' => $username, 'PasswordHash' => $hash], 'Repositorio de usuarios incorrecto.');
    check($users->findByUsername("{$username}' OR '1'='1") === null, 'La búsqueda no debe admitir inyección SQL.');
    rejects(static fn () => $users->create($username, $hash), 'La clave primaria de Users debe impedir duplicados.');

    $now = '2026-10-11 12:00:00.000000';
    $insert = $connection->prepare(
        'INSERT INTO `Topics` (`Name`, `CreatedAtUtc`, `UpdatedAtUtc`) VALUES (:name, :created, :updated)',
    );
    $insert->execute(['name' => 'Álgebra', 'created' => $now, 'updated' => $now]);
    $topicId = (int) $connection->lastInsertId();
    check($topics->findById($topicId)['Name'] === 'Álgebra', 'Repositorio de temas incorrecto.');
    check($topics->findById(PHP_INT_MAX) === null, 'Un tema inexistente debe devolver null.');
    rejects(static fn () => $insert->execute(['name' => 'ÁLGEBRA', 'created' => $now, 'updated' => $now]), 'El nombre de tema debe ser único según la colación.');
    rejects(static fn () => $insert->execute(['name' => ' mal ', 'created' => $now, 'updated' => $now]), 'CHECK debe rechazar espacios laterales.');

    $question = $connection->prepare(
        'INSERT INTO `Questions` (`TopicId`, `Statement`, `CreatedAtUtc`, `UpdatedAtUtc`) VALUES (:topicId, :statement, :created, :updated)',
    );
    rejects(static fn () => $question->execute(['topicId' => $topicId + 1000, 'statement' => 'Sin tema', 'created' => $now, 'updated' => $now]), 'FK debe rechazar preguntas huérfanas.');
    $question->execute(['topicId' => $topicId, 'statement' => '¿Cuánto es 1 + 1?', 'created' => $now, 'updated' => $now]);
    $questionId = (int) $connection->lastInsertId();

    $option = $connection->prepare(
        'INSERT INTO `QuestionOptions` (`QuestionId`, `Text`, `Position`, `IsCorrect`) VALUES (:questionId, :text, :position, :correct)',
    );
    $option->execute(['questionId' => $questionId, 'text' => 'Dos', 'position' => 1, 'correct' => 1]);
    rejects(static fn () => $option->execute(['questionId' => $questionId, 'text' => 'Otro', 'position' => 1, 'correct' => 0]), 'La posición repetida debe rechazarse.');
    rejects(static fn () => $option->execute(['questionId' => $questionId, 'text' => 'Otro', 'position' => 5, 'correct' => 0]), 'CHECK debe rechazar posiciones fuera de 1..4.');

    return 'confirmado';
});
check($result === 'confirmado', 'La transacción debe devolver el resultado.');
check($users->findByUsername($username) !== null, 'La transacción debe confirmar los cambios.');

try {
    $transactions->run(static function (PDO $connection) use ($username): never {
        $query = $connection->prepare('DELETE FROM `Users` WHERE `Username` = :username');
        $query->execute(['username' => $username]);
        throw new RuntimeException('provocar rollback');
    });
    throw new RuntimeException('La excepción de la operación debe propagarse.');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'provocar rollback', 'La excepción original debe propagarse.');
}
check($users->findByUsername($username) !== null, 'El rollback debe restaurar la fila.');

$delete = $db->prepare('DELETE FROM `Users` WHERE `Username` = :username');
$delete->execute(['username' => $username]);
check($users->findByUsername($username) === null, 'La limpieza de la prueba debe completar.');

echo "MySQL: conexión segura, utf8mb4, nueve tablas, 10 FK, 10 CHECK, repositorios y transacciones OK\n";
