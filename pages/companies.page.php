<section class="page-header">
    <div class="container">
        <h1>
            <?php echo e($sector_data['description'] ?? ''); ?>
            <?php if (!empty($town_data)): ?>
                in <?php echo e(ucwords(strtolower((string) $town_data))); ?>
            <?php endif; ?>
        </h1>
        <p class="breadcrumbs">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <?php echo e($section_data['name'] ?? ''); ?>
            <span aria-hidden="true">›</span>
            <?php echo e($sector_data['description'] ?? ''); ?>
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php
        $rows = $comp_query->fetchAll(PDO::FETCH_ASSOC);
        if ($rows):
        ?>
            <ul class="link-grid">
                <?php foreach ($rows as $comp): ?>
                    <li>
                        <a href="/company/number/<?php echo eUrl($comp['CompanyNumber']); ?>">
                            <?php echo e($comp['CompanyName']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-info">
                No companies found for this location and sector.
            </div>
        <?php endif; ?>
    </div>
</section>
