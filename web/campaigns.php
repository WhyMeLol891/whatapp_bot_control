<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
$error = null;
$notice = null;

function render_campaign_body(string $body, array $contact): string
{
    return strtr($body, [
        '{{name}}' => $contact['name'],
        '{{phone}}' => $contact['phone'],
        '{{company}}' => (string) ($contact['company'] ?? ''),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'template') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            if ($name === '' || strlen($name) > 160 || $body === '' || strlen($body) > 10000) {
                throw new InvalidArgumentException('Template name and message are required.');
            }
            $stmt = $pdo->prepare('INSERT INTO message_templates (name, body, created_by) VALUES (?, ?, ?)');
            $stmt->execute([$name, $body, $user['id']]);
            $notice = 'Template saved.';
        } elseif ($action === 'campaign') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $scheduledInput = trim((string) ($_POST['scheduled_at'] ?? ''));
            $contactIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['contact_ids'] ?? [])))));
            if ($name === '' || strlen($name) > 160 || $body === '' || !$contactIds) {
                throw new InvalidArgumentException('Campaign name, message, and at least one opted-in contact are required.');
            }
            $scheduledAt = null;
            if ($scheduledInput !== '') {
                $local = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $scheduledInput, new DateTimeZone(APP_TIMEZONE));
                if (!$local || $local < new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE))) {
                    throw new InvalidArgumentException('Choose a future schedule time.');
                }
                $scheduledAt = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
            $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
            $stmt = $pdo->prepare("SELECT id, name, phone, company FROM contacts WHERE is_active = 1 AND consent_status = 'opt_in' AND id IN ($placeholders) ORDER BY id");
            $stmt->execute($contactIds);
            $contacts = $stmt->fetchAll();
            if (count($contacts) !== count($contactIds)) {
                throw new InvalidArgumentException('Only active opted-in contacts may receive campaigns.');
            }
            $pdo->beginTransaction();
            $campaign = $pdo->prepare("INSERT INTO campaigns (name, body, status, scheduled_at, created_by) VALUES (?, ?, 'queued', ?, ?)");
            $campaign->execute([$name, $body, $scheduledAt, $user['id']]);
            $campaignId = (int) $pdo->lastInsertId();
            $job = $pdo->prepare('INSERT INTO message_jobs (campaign_id, contact_id, phone, rendered_body, available_at) VALUES (?, ?, ?, ?, COALESCE(?, UTC_TIMESTAMP()))');
            foreach ($contacts as $contact) {
                $job->execute([$campaignId, $contact['id'], $contact['phone'], render_campaign_body($body, $contact), $scheduledAt]);
            }
            $pdo->commit();
            $notice = 'Campaign queued for ' . count($contacts) . ' opted-in contact(s).';
        } else {
            throw new InvalidArgumentException('Unsupported action.');
        }
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($exception->getMessage());
        $error = 'Unable to save the campaign.';
    }
}

$templates = $pdo->query('SELECT id, name, body FROM message_templates ORDER BY name, id')->fetchAll();
$contacts = $pdo->query("SELECT id, name, phone, company FROM contacts WHERE is_active = 1 AND consent_status = 'opt_in' ORDER BY name, id")->fetchAll();
$campaigns = $pdo->query("SELECT c.id, c.name, c.status, c.scheduled_at, c.created_at, COUNT(j.id) AS total, SUM(j.status = 'sent') AS sent, SUM(j.status = 'failed') AS failed, SUM(j.status IN ('pending','processing')) AS pending FROM campaigns c LEFT JOIN message_jobs j ON j.campaign_id = c.id GROUP BY c.id ORDER BY c.id DESC LIMIT 30")->fetchAll();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Campaigns | WhatsApp Bot Control</title><link rel="stylesheet" href="assets/style.css"></head>
<body><header class="topbar"><strong>WBC</strong><nav><a href="index.php">Dashboard</a><a href="contacts.php">Contacts</a><a href="campaigns.php">Campaigns</a><form method="post" action="logout.php" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="link-button">Sign out</button></form></nav></header>
<main class="shell"><div class="page-heading"><div><p class="eyebrow">CONSENT-BASED MESSAGING</p><h1>Campaigns</h1><p class="muted">Only active contacts with explicit opt-in consent can be queued.</p></div></div>
<?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="panel"><h2>New campaign</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="campaign"><div class="form-grid"><label>Campaign name<input name="name" maxlength="160" required></label><label>Schedule <span class="muted">optional</span><input type="datetime-local" name="scheduled_at"></label></div><label>Message<textarea name="body" rows="6" maxlength="10000" required placeholder="Hello {{name}}..."></textarea></label><p class="muted">Supported variables: {{name}}, {{phone}}, {{company}}</p><label>Opted-in recipients<select name="contact_ids[]" multiple size="6" required><?php foreach ($contacts as $contact): ?><option value="<?= (int) $contact['id'] ?>"><?= htmlspecialchars($contact['name'] . ' — ' . $contact['phone'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><button type="submit">Queue campaign</button></form></section>
<section class="panel"><h2>Save template</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="template"><div class="form-grid"><label>Template name<input name="name" maxlength="160" required></label></div><label>Template body<textarea name="body" rows="4" maxlength="10000" required></textarea></label><button type="submit">Save template</button></form><?php if ($templates): ?><p class="muted">Saved templates: <?= htmlspecialchars(implode(', ', array_column($templates, 'name')), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?></section>
<section class="panel"><h2>Recent campaigns</h2><?php if (!$campaigns): ?><p class="muted">No campaigns yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Name</th><th>Status</th><th>Total</th><th>Sent</th><th>Failed</th><th>Pending</th></tr></thead><tbody><?php foreach ($campaigns as $campaign): ?><tr><td><?= htmlspecialchars($campaign['name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($campaign['status'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $campaign['total'] ?></td><td><?= (int) $campaign['sent'] ?></td><td><?= (int) $campaign['failed'] ?></td><td><?= (int) $campaign['pending'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main></body></html>
