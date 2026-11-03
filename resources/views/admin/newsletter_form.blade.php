<?php /** @var ?\App\Models\Newsletter $n @var int $reach @var array $recent */
$locked = $n && in_array($n['status'], ['sending', 'sent'], true);
$v = fn ($k, $d = '') => old($k, $n[$k] ?? $d);
$sched = $n && $n['scheduled_at'] ? date('Y-m-d\TH:i', strtotime((string) $n['scheduled_at'])) : '';
?>
<div class="cols">
<form method="post" action="<?= e(url('admin/newsletters/save')) ?>" class="stack wide"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($n['id'] ?? 0) ?>">
  <section class="apanel"><h2>Details</h2>
    <fieldset <?= $locked ? 'disabled' : '' ?> style="border:0;padding:0;margin:0">
    <?= field_text('name', 'Internal name', $v('name'), ['required' => true, 'placeholder' => 'e.g. November newsletter']) ?>
    <?= field_text('subject', 'Subject line', $v('subject'), ['placeholder' => 'This week at {store}']) ?>
    <?= field_text('preheader', 'Preview text', $v('preheader'), ['placeholder' => 'Shown after the subject in the inbox']) ?>
    <?= field_select('segment', 'Send to', \App\Models\Newsletter::SEGMENTS, $v('segment', 'all'), ['hint' => 'Only people who are subscribed receive newsletters. ' . (int) $reach . ' address(es) match the current segment.']) ?>
    </fieldset>
  </section>
  <?php if (!$locked): ?>
  <section class="apanel"><h2>Design</h2>
    <p class="muted small"><?= $n && $n->blocks() ? '✓ This newsletter has a design (' . count($n->blocks()) . ' blocks).' : 'No design yet — open the builder to start from a template or a blank canvas.' ?></p>
    <button class="btn" name="do" value="design">🎨 <?= $n && $n->blocks() ? 'Edit design' : 'Design email' ?></button>
  </section>
  <section class="apanel"><h2>Send</h2>
    <div class="g2"><?= field_text('scheduled_at', 'Schedule for (optional)', $sched, ['type' => 'datetime-local', 'hint' => 'Server time ' . date('H:i') . '.']) ?><span></span></div>
    <div class="actions"><button class="btn ghost" name="do" value="save">Save draft</button> <button class="btn ghost" name="do" value="schedule">Schedule</button>
      <button class="btn" name="do" value="send" onclick="return confirm('Send this newsletter to <?= (int) $reach ?> subscriber(s) now?')">Send now</button></div>
  </section>
  <?php endif; ?>
</form>
<div class="stack">
  <?php if ($n): ?>
  <section class="apanel"><h2>Status</h2>
    <p><span class="badge s-<?= $n['status'] === 'sent' ? 'completed' : 'processing' ?>" id="nl-status"><?= e($n['status']) ?></span></p>
    <dl class="kv"><div><dt>Recipients</dt><dd id="nl-rec"><?= (int) $n['recipients'] ?></dd></div><div><dt>Sent</dt><dd id="nl-sent"><?= (int) $n['sent'] ?></dd></div><div><dt>Failed</dt><dd id="nl-fail"><?= (int) $n['failed'] ?></dd></div></dl>
    <?php if ($n['status'] === 'sending'): ?><p class="muted small" id="nl-note">Sending… keep this page open to speed things up.</p><?php endif; ?>
    <div class="row-actions" style="margin-top:10px;gap:8px;flex-wrap:wrap">
      <?php if (in_array($n['status'], ['scheduled', 'sending'])): ?><form method="post" action="<?= e(url('admin/newsletters/' . $n['id'] . '/pause')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Pause / back to draft</button></form><?php endif; ?>
      <form method="post" action="<?= e(url('admin/newsletters/' . $n['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Duplicate</button></form>
      <form method="post" action="<?= e(url('admin/newsletters/' . $n['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this newsletter?')"><?= csrf_field() ?><button class="btn sm ghost danger">Delete</button></form>
    </div>
  </section>
  <?php if ($recent): ?><section class="apanel"><h2>Latest deliveries</h2><ul class="list"><?php foreach ($recent as $l): ?><li><span><?= e($l['email']) ?></span><span class="muted"><?= $l['ok'] ? '' : 'failed · ' ?><?= e(time_ago($l['created_at'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php endif; ?>
  <p><a href="<?= e(url('admin/newsletters')) ?>">← All newsletters</a></p>
</div>
</div>
<?php if ($n && $n['status'] === 'sending'): ?>
<script>
(function(){var busy=false,tok=<?= json_encode(csrf_token()) ?>;function tick(){if(busy)return;busy=true;fetch(<?= json_encode(url('admin/newsletters/' . $n['id'] . '/batch')) ?>,{method:'POST',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){busy=false;document.getElementById('nl-sent').textContent=j.sent;document.getElementById('nl-fail').textContent=j.failed;document.getElementById('nl-rec').textContent=j.recipients;if(j.status!=='sending'){document.getElementById('nl-status').textContent=j.status;var m=document.getElementById('nl-note');if(m)m.textContent='Done — newsletter sent.';clearInterval(t)}}).catch(function(){busy=false})}var t=setInterval(tick,4000);tick();})();
</script>
<?php endif; ?>
