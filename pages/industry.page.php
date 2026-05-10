<section class="page-header">
    <div class="container">
        <h1><?php echo e($sector_data['name'] ?? 'Industry'); ?></h1>
        <p class="breadcrumbs">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <?php echo e($sector_data['name'] ?? ''); ?>
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($sector_data['description'])): ?>
            <div class="panel mb-4">
                <p class="mb-0"><?php echo nl2br(e((string) $sector_data['description'])); ?></p>
            </div>
        <?php endif; ?>

        <h2>Featured companies</h2>
        <?php
        $compRows = $comp_query->fetchAll(PDO::FETCH_ASSOC);
        if ($compRows):
        ?>
            <ul class="link-grid">
                <?php foreach ($compRows as $comp): ?>
                    <li>
                        <a href="/company/number/<?php echo eUrl($comp['CompanyNumber']); ?>">
                            <?php echo e($comp['CompanyName']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No companies are available for this sector right now.</p>
        <?php endif; ?>

        <h2 class="mt-4">Sector codes</h2>
        <ul class="link-grid">
            <?php while ($sic = $sic_query->fetch(PDO::FETCH_ASSOC)): ?>
                <li>
                    <a href="/sic_code/code/<?php echo eUrl($sic['code']); ?>/<?php echo e($sic['slug'] ?? ''); ?>/">
                        <?php echo e($sic['description'] ?? ''); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>
</section>
