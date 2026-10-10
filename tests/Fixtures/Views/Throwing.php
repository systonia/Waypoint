<?php /** @var \Waypoint\Http\View $this */ ?>
<p>PARTIAL-TEMPLATE-OUTPUT</p>
<?php $this->startSection('sidebar'); ?>
<p>PARTIAL-SECTION-OUTPUT</p>
<?php throw new RuntimeException('template failed half way');
