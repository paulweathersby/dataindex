<section class="page-header">
    <div class="container">
        <h1><?php echo e($data['title'] ?? ''); ?></h1>
        <p class="breadcrumbs">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <a href="/resources">Resources</a>
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid" style="grid-template-columns:2fr 1fr;gap:2.5rem;">
            <article class="prose">
                <?php
                // Article bodies come from the `blog` table. They are authored content so
                // we intentionally allow HTML here — but mark the intent with a comment.
                // Consider switching to a markdown-rendered field in the future.
                echo $data['body'] ?? '';
                ?>
            </article>

            <aside>
                <h4>Related articles</h4>
                <ul class="stack-tight" style="list-style:none;padding:0;">
                    <?php while ($list = $list_query->fetch(PDO::FETCH_ASSOC)): ?>
                        <li>
                            <a href="/article/item/<?php echo eUrl($list['slug'] ?? ''); ?>">
                                <?php echo e($list['title'] ?? ''); ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </aside>
        </div>
    </div>
</section>
<style>
.prose h1, .prose h2, .prose h3 { margin-top: 1.5em; }
.prose p, .prose li { color: var(--c-ink-soft); }
.prose img { border-radius: var(--radius); margin: 1.5rem 0; }
.prose a   { text-decoration: underline; }
</style>
