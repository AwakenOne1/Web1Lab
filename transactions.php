<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Выход из системы
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT login, name FROM users WHERE Id = $user_id");
$user = $result->fetch_assoc();

// Обработка поиска
$minSum = 0.00;
$maxSum = 99999999999.99;
$destination = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Проверка на сброс формы
    if (isset($_POST['resetSearch']) && $_POST['resetSearch'] === '1') {
        // Если форма сброшена, устанавливаем значения по умолчанию
        $minSum = 0.00;
        $maxSum = 99999999999.99;
        $destination = '';
    } else {
        // Иначе обрабатываем значения с формы
        $minSum = number_format((float) $_POST['minSum'], 2, '.', '');
        $maxSum = number_format((float) $_POST['maxSum'], 2, '.', '');
        $destination = $_POST['destination'];
    }

    // Подготовка SQL-запроса
    $sql = "SELECT * FROM transactions WHERE UserId = ? AND Sum BETWEEN ? AND ?";
    $params = [$user_id, $minSum, $maxSum];

    if (!empty($destination)) {
        $sql .= " AND Destination REGEXP ?";
        $params[] = $destination;
    }

    // Подготовка и выполнение запроса
    $stmt = $conn->prepare($sql);

    // Генерация строки типов для bind_param
    $types = 'i'; // тип для user_id
    $types .= 'dd'; // типы для minSum и maxSum
    if (!empty($destination)) {
        $types .= 's'; // тип для destination
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Получение всех транзакций, если поиск не выполнялся
    $transactions_result = $conn->query("SELECT * FROM transactions WHERE UserId = $user_id");
    $transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);
}

// Закрытие соединения после выполнения всех запросов
$conn->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Транзакции</title>
    <link rel="stylesheet" href="static.css">
    <style>
        /* Стили для страницы */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        header {
            display: flex;
            justify-content: space-between;
            padding: 1.5em;
            background-color: #4CAF50;
            color: white;
        }
        header a {
            color: white;
            text-decoration: none;
        }
        main {
            padding: 2em;
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Выравнивание по левому краю */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2em;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 1em;
            text-align: left;
            word-wrap: break-word;
        }
        th:nth-child(1), td:nth-child(1) {
            width: 10%;
        }
        th:nth-child(2), td:nth-child(2) {
            width: 15%;
        }
        th:nth-child(3), td:nth-child(3) {
            width: 25%;
        }
        th:nth-child(4), td:nth-child(4) {
            width: 30%;
        }
        th:nth-child(5), td:nth-child(5) {
            width: 10%;
        }
        .edit-button, .delete-button {
            padding: 0.8em 0.8em;
            font-size: 1em;
            border: none;
            border-radius: 0.25em;
            cursor: pointer;
        }
        .edit-button {
            background-color: #4CAF50;
            color: white;
            margin-right: 0.5em;
        }
        .edit-button:hover {
            background-color: #45a049;
        }
        .delete-button {
            background-color: #f44336;
            color: white;
        }
        .delete-button:hover {
            background-color: #e53935;
        }
        .button-container {
            display: flex;
            align-items: center;
            width: 100%;
            margin: 1em 0;
        }
        .search-button {
            margin-right: 0.5em; /* Уменьшено расстояние между кнопками */
        }
        .create-button {
            /* Никаких дополнительных маргинов для центрирования */
        }
        .create-button, .search-button {
            background-color: #4CAF50;
            color: white;
            padding: 1em 2em;
            font-size: 1.2em;
            border: none;
            border-radius: 0.25em;
            cursor: pointer;
            width: auto;
        }
        .create-button:hover, .search-button:hover {
            background-color: #45a049;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 2em;
            border-radius: 0.5em;
            width: 25%;
            max-width: 600px;
        }
        .close {
            float: right;
            font-size: 1.2em;
            cursor: pointer;
        }
    </style>
</head>
<body>

<header>
    <div><?php echo htmlspecialchars($user['login']); ?> (<?php echo htmlspecialchars($user['name']); ?>)</div>
    <div><a href="?action=logout">Выйти</a></div>
</header>

<main>
    <h1>Ваши транзакции</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Сумма</th>
                <th>Кому</th>
                <th>Комментарий</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?php echo htmlspecialchars($transaction['Id']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['Sum']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['Destination']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['Comment']); ?></td>
                    <td>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <button class="edit-button" onclick="openEditModal(<?php echo $transaction['Id']; ?>, '<?php echo htmlspecialchars($transaction['Sum']); ?>', '<?php echo htmlspecialchars($transaction['Destination']); ?>', '<?php echo htmlspecialchars($transaction['Comment']); ?>')">Редактировать</button>
                            <button class="delete-button" onclick="deleteTransaction(<?php echo $transaction['Id']; ?>)">Удалить</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="button-container">
        <button class="search-button" onclick="openSearchModal()">Поиск по критериям</button>
        <button class="create-button" onclick="openCreateModal()">Создать транзакцию</button>
    </div>
</main>

<?php include 'transactions_modal.php'; ?>

<script>
    function openCreateModal() {
        document.getElementById('createModal').style.display = 'flex';
    }

    function closeCreateModal() {
        document.getElementById('createModal').style.display = 'none';
    }

    function openSearchModal() {
        document.getElementById('searchModal').style.display = 'flex';
    }

    function closeSearchModal() {
        document.getElementById('searchModal').style.display = 'none';
    }

    function openEditModal(id, sum, destination, comment) {
        document.getElementById('transaction_id').value = id;
        document.getElementById('edit_sum').value = sum;
        document.getElementById('edit_destination').value = destination;
        document.getElementById('edit_comment').value = comment;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function deleteTransaction(id) {
        if (confirm('Вы уверены, что хотите удалить эту транзакцию?')) {
            window.location.href = 'delete_transaction.php?id=' + id;
        }
    }
    
    window.onclick = function(event) {
        if (event.target == document.getElementById('createModal')) {
            closeCreateModal();
        } else if (event.target == document.getElementById('searchModal')) {
            closeSearchModal();
        } else if (event.target == document.getElementById('editModal')) {
            closeEditModal();
        }
    };
</script>

</body>
</html>