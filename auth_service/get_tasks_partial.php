<?php
require_once 'db_connect.php';

try {
    $stmt = $pdo->query("SELECT * FROM tasks ORDER BY id DESC");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tasks)): ?>
        <tr><td colspan="3" style="text-align:center; padding: 20px;">Список пуст</td></tr>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <tr id="task-<?php echo $task['id']; ?>">
                <td style="padding: 15px; border-bottom: 1px solid #eee;">
                    <a href="view.php?id=<?php echo $task['id']; ?>"
                       style="color: #1a73e8; text-decoration: none; font-weight: bold; border-bottom: 1px dashed #1a73e8;">
                        #<?php echo htmlspecialchars($task['id']); ?>
                    </a>
                </td>

                <td style="padding: 15px; border-bottom: 1px solid #eee; white-space: nowrap;">
                    <a href="edit.php?id=<?php echo $task['id']; ?>"
                       style="color: #1a73e8; text-decoration: none; font-weight: 500;">Изменить</a>
                    <span style="color: #ccc; margin: 0 8px;">|</span>
                    <a href="#" class="btn-delete"
                       style="color: #dc2626; text-decoration: none; font-weight: 500;"
                       onclick="event.preventDefault(); deleteAndRefresh(<?php echo $task['id']; ?>);">Удалить</a>
                </td>

                <td style="padding: 15px; border-bottom: 1px solid #eee; text-align: center;">
                    <form action="mail_handler.php" method="POST"
                          style="display: flex; flex-direction: column; gap: 8px; align-items: center; padding-top: 15px;">

                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">

                        <input type="email" name="email" placeholder="example@mail.com" required
                               style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; width: 100%; max-width: 200px;">

                        <input type="text" name="message_text" placeholder="Введите сообщение..."
                               style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; width: 100%; max-width: 200px;">

                        <button type="submit"
                                style="padding: 6px 20px; background: #34a853; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; width: 100%; max-width: 200px;">
                            Отправить
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif;
} catch (PDOException $e) {
    echo "<tr><td colspan='3'>Ошибка базы: " . $e->getMessage() . "</td></tr>";
}