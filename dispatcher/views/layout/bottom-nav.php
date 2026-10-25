<?php
/* Bottom navigation shell. Expects: $activeTab, $dashBase, $translations. */
$navTabs = [
    ['id' => 'home',       'icon' => 'bi-house-door',     'label' => $translations['nav.home'] ?? 'Home'],
    ['id' => 'attendance', 'icon' => 'bi-person-check',   'label' => $translations['nav.attendance'] ?? 'Attendance'],
    ['id' => 'queue',      'icon' => 'bi-list-check',     'label' => $translations['nav.queue'] ?? 'Queue'],
    ['id' => 'trips',      'icon' => 'bi-signpost-split', 'label' => $translations['nav.trips'] ?? 'Trips'],
    ['id' => 'more',       'icon' => 'bi-grid-3x3-gap',   'label' => $translations['nav.more'] ?? 'More'],
];
?>
<nav class="bottom-nav" aria-label="<?php echo htmlspecialchars($translations['nav.label'] ?? 'Main navigation', ENT_QUOTES, 'UTF-8'); ?>">
  <?php foreach ($navTabs as $navTab): ?>
  <a class="nav-item<?php echo $navTab['id'] === 'queue' ? ' nav-item-center' : ''; ?><?php echo $activeTab === $navTab['id'] ? ' active' : ''; ?>"
     href="<?php echo htmlspecialchars($dashBase . $navTab['id'], ENT_QUOTES, 'UTF-8'); ?>"
     <?php echo $activeTab === $navTab['id'] ? 'aria-current="page"' : ''; ?>>
    <?php if ($navTab['id'] === 'queue'): ?>
    <span class="nav-queue-action" aria-hidden="true">
      <span class="nav-queue-ring">
        <i class="bi <?php echo $navTab['icon']; ?>"></i>
      </span>
    </span>
    <?php else: ?>
    <i class="bi <?php echo $navTab['icon']; ?>" aria-hidden="true"></i>
    <?php endif; ?>
    <span class="nav-label"><?php echo htmlspecialchars($navTab['label'], ENT_QUOTES, 'UTF-8'); ?></span>
    <?php if ($navTab['id'] === 'more'): ?>
    <span class="nav-badge" id="moreNavBadge" hidden>0</span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</nav>
