<section class="page-header">
    <div class="container">
        <h1>Resources</h1>
        <p class="breadcrumbs"><a href="/">Home</a> <span aria-hidden="true">›</span> Resources</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <nav class="tabs__nav" aria-label="Resource sections" style="margin-bottom:2rem;">
            <?php
            $sections = [
                ''          => 'All',
                'general'   => 'General',
                'companies' => 'Companies',
                'people'    => 'People',
                'places'    => 'Places',
            ];
            $currentSection = (string) ($pathArray['section'] ?? '');
            foreach ($sections as $slug => $label):
                $href = $slug === '' ? '/resources' : '/resources/section/' . rawurlencode($slug);
                $isActive = $currentSection === $slug;
            ?>
                <a href="<?php echo eAttr($href); ?>"
                   class="<?php echo $isActive ? 'is-active' : ''; ?>">
                    <?php echo e($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php
        $rows = $list_query->fetchAll(PDO::FETCH_ASSOC);
        if ($rows):
        ?>
            <div class="grid grid-2">
                <?php foreach ($rows as $list): ?>
                    <article class="card">
                        <h4 class="mt-0">
                            <a href="/article/item/<?php echo eUrl($list['slug'] ?? ''); ?>">
                                <?php echo e($list['title'] ?? ''); ?>
                            </a>
                        </h4>
                        <p><?php echo e($list['description'] ?? ''); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No articles available in this section yet.</div>
        <?php endif; ?>
    </div>
</section>
