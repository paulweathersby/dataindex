<section class="page-header">
    <div class="container">
        <h1><?php echo e($sector_data['description'] ?? 'SIC Code'); ?></h1>
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
        <?php if (!empty($sector_data['long_description'])): ?>
            <div class="panel mb-4">
                <p class="mb-0"><?php echo nl2br(e((string) $sector_data['long_description'])); ?></p>
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
            <p class="text-muted">No companies are currently listed for this SIC code.</p>
        <?php endif; ?>

        <h2 class="mt-4">Locations</h2>
        <?php
        $townRows = $code_query->fetchAll(PDO::FETCH_ASSOC);
        if ($townRows):
        ?>
            <ul class="link-grid">
                <?php foreach ($townRows as $row):
                    if (empty($row['town'])) continue;
                    $slug = str_replace(' ', '-', (string) $row['town']);
                ?>
                    <li>
                        <a href="/companies/code/<?php echo eUrl($sector_data['code'] ?? ''); ?>/town/<?php echo e($slug); ?>">
                            <?php echo e($row['town']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No town data available for this code.</p>
        <?php endif; ?>
    </div>
</section>
