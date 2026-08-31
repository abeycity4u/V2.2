<?php
/**
 * Platform-wide notification renderer.
 *
 * Session flash messages are rendered once by navbar.php so every authenticated
 * page gets the same notification experience. Local notifications can call
 * renderNotification() directly.
 */
if (!function_exists('notificationMeta')) {
    function notificationMeta(string $type): array
    {
        $type = strtolower($type);
        return match ($type) {
            'success' => [
                'class' => 'app-notification-success',
                'icon' => 'bi-check-circle-fill',
                'label' => 'Success',
                'tip' => 'Your changes have been saved successfully.',
            ],
            'warning' => [
                'class' => 'app-notification-warning',
                'icon' => 'bi-exclamation-triangle-fill',
                'label' => 'Warning',
                'tip' => 'Please review this before continuing.',
            ],
            'info' => [
                'class' => 'app-notification-info',
                'icon' => 'bi-info-circle-fill',
                'label' => 'Information',
                'tip' => 'Review the information above before continuing.',
            ],
            default => [
                'class' => 'app-notification-error',
                'icon' => 'bi-exclamation-triangle-fill',
                'label' => 'Action could not be completed',
                'tip' => 'Please check the information entered and try again.',
            ],
        };
    }
}

if (!function_exists('renderNotification')) {
    function renderNotification(string $type, string $message, ?string $title = null, ?string $tip = null): void
    {
        $meta = notificationMeta($type);
        $message = trim($message);
        if ($message === '') return;

        // Make the actual message the headline when it is a concise sentence.
        // This keeps useful server-side messages such as "Insufficient stock."
        // immediately visible while preserving longer details below it.
        if ($title === null || trim($title) === '') {
            $parts = preg_split('/(?<=[.!?])\s+/', $message, 2);
            $candidate = trim($parts[0] ?? '');
            if ($candidate !== '' && strlen($candidate) <= 96) {
                $title = $candidate;
                $remaining = trim($parts[1] ?? '');
                $message = $remaining !== '' ? $remaining : ($type === 'success' ? 'The requested action was completed successfully.' : ($type === 'error' ? 'Please check the information entered and try again.' : 'Please review the information above.'));
            } else {
                $title = $meta['label'];
            }
        }
        if ($tip === null || trim($tip) === '') {
            $tip = $meta['tip'];
        }
        $dismissible = $type !== 'error' ? ' data-auto-dismiss="true"' : '';
        $autoClass = $type !== 'error' ? ' app-notification-auto' : '';
        ?>
        <div class="app-notification <?php echo htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8'); ?><?php echo $autoClass; ?>" role="alert" aria-live="assertive"<?php echo $dismissible; ?>>
            <div class="app-notification-icon" aria-hidden="true"><i class="bi <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></div>
            <div class="app-notification-content">
                <div class="app-notification-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="app-notification-message"><?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></div>
            </div>
            <div class="app-notification-tip">
                <div class="app-notification-tip-label"><?php echo $type === 'error' ? 'Tip' : ($type === 'success' ? 'Done' : 'Action'); ?></div>
                <div><?php echo htmlspecialchars($tip, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <button type="button" class="app-notification-close" data-notification-close aria-label="Dismiss notification"><i class="bi bi-x-lg"></i></button>
        </div>
        <?php
    }
}

if (!function_exists('renderSessionNotifications')) {
    function renderSessionNotifications(): void
    {
        $messages = [
            ['error', $_SESSION['error'] ?? null],
            ['success', $_SESSION['success'] ?? null],
            ['warning', $_SESSION['warning'] ?? null],
            ['info', $_SESSION['info'] ?? null],
        ];
        foreach ($messages as [$type, $message]) {
            if ($message !== null && $message !== '') {
                renderNotification($type, (string)$message);
                unset($_SESSION[$type]);
            }
        }
    }
}
