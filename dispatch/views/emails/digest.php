<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Dispatch Digest</title>
<style>
  body { margin: 0; padding: 0; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  .header { background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 40px 40px 32px; text-align: center; }
  .header h1 { color: #fff; font-size: 28px; font-weight: 700; margin: 0; }
  .header p { color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 15px; }
  .body { padding: 40px; }
  .body > p { color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 24px; }
  .item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 12px; }
  .item-header { display: flex; justify-content: space-between; align-items: center; }
  .item-campaign { font-weight: 700; font-size: 16px; color: #1e293b; }
  .item-venue { font-size: 14px; color: #64748b; margin: 4px 0; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
  .badge-overdue { background: #fee2e2; color: #dc2626; }
  .badge-today { background: #fef3c7; color: #d97706; }
  .badge-soon { background: #dbeafe; color: #2563eb; }
  .item-date { font-size: 13px; color: #94a3b8; margin: 6px 0 0; }
  .item-link a { font-size: 13px; color: #4f46e5; text-decoration: none; }
  .cta { text-align: center; padding: 24px 0 8px; }
  .btn { display: inline-block; padding: 14px 32px; background: #4f46e5; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; }
  .footer { padding: 24px 40px; border-top: 1px solid #e2e8f0; text-align: center; }
  .footer p { color: #94a3b8; font-size: 13px; margin: 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>📬 Your Dispatch Digest</h1>
    <p><?= date('l, F j, Y') ?></p>
  </div>
  <div class="body">
    <p>Hi <?= htmlspecialchars($user['name']) ?>, here are your upcoming submission deadlines:</p>

    <?php foreach ($items as $item): ?>
      <?php
        $today = date('Y-m-d');
        $due = $item['due_date'];
        $daysUntil = (int) round((strtotime($due) - strtotime($today)) / 86400);
        if ($daysUntil < 0) $badgeClass = 'badge-overdue';
        elseif ($daysUntil === 0) $badgeClass = 'badge-today';
        else $badgeClass = 'badge-soon';
        $badgeText = $daysUntil < 0 ? 'Overdue' : ($daysUntil === 0 ? 'Due Today' : "In {$daysUntil} days");
      ?>
      <div class="item">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="item-campaign"><?= htmlspecialchars($item['campaign_name']) ?></div>
            <div class="item-venue">Submit to: <?= htmlspecialchars($item['venue_name']) ?></div>
            <div class="item-date">Due: <?= date('M j, Y', strtotime($due)) ?> · Event: <?= date('M j, Y', strtotime($item['event_date'])) ?></div>
            <?php if (!empty($item['submission_url'])): ?>
            <div class="item-link"><a href="<?= htmlspecialchars($item['submission_url']) ?>">Open submission portal &rarr;</a></div>
            <?php endif; ?>
          </div>
          <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="cta">
      <a href="<?= htmlspecialchars($appUrl) ?>/dashboard" class="btn">View Full Dashboard</a>
    </div>
  </div>
  <div class="footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>. <a href="<?= htmlspecialchars($appUrl) ?>/profile" style="color:#4f46e5">Manage notification preferences</a></p>
  </div>
</div>
</body>
</html>
