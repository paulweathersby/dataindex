<?php
$hasSector = !empty($sector_data['sector']);
$hasSic    = !empty($sic_data['code']);
?>
<section class="page-header">
    <div class="container">
        <h1>
            <?php echo e($comp_data['CompanyName']); ?>
            <span class="badge badge--brand"><?php echo e($comp_data['CompanyNumber']); ?></span>
        </h1>
        <p class="breadcrumbs">
            <a href="/">Home</a>
            <?php if ($hasSector): ?>
                <span aria-hidden="true">›</span>
                <a href="/industry/sector/<?php echo e(strtolower((string) $sector_data['sector'])); ?>/<?php echo e($sector_data['slug'] ?? ''); ?>/">
                    <?php echo e($sector_data['name'] ?? ''); ?>
                </a>
            <?php endif; ?>
            <?php if ($hasSic): ?>
                <span aria-hidden="true">›</span>
                <a href="/sic_code/code/<?php echo e($sic_data['code']); ?>/<?php echo e($sic_data['slug'] ?? ''); ?>/">
                    <?php echo e($sic_data['description'] ?? ''); ?>
                </a>
            <?php endif; ?>
            <span aria-hidden="true">›</span>
            <span><?php echo e($comp_data['CompanyName']); ?></span>
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="tabs" data-tabs>
            <ul class="tabs__nav" role="tablist">
                <li><a href="#overview" role="tab" class="is-active" aria-selected="true">Overview</a></li>
                <li><a href="#financial" role="tab">Financials</a></li>
                <li><a href="#shareholders" role="tab">Shareholders</a></li>
                <li><a href="#filing-history" role="tab">Filing History</a></li>
            </ul>

            <!-- ======================================================== Overview ======================================================== -->
            <article id="overview" class="tabs__panel is-active" role="tabpanel">
                <div class="grid grid-2">
                    <div>
                        <span class="eyebrow">Registered address</span>
                        <h2 class="mt-0"><?php echo e($comp_data['CompanyName']); ?></h2>

                        <p class="mt-2">
                            <?php
                            $addressParts = array_filter([
                                $comp_data['RegAddress_CareOf']     ?? '',
                                $comp_data['RegAddress_POBox']      ?? '',
                                $comp_data['RegAddress_AddressLine1'] ?? '',
                                $comp_data['RegAddress_AddressLine2'] ?? '',
                                $comp_data['RegAddress_Town']       ?? '',
                                $comp_data['RegAddress_County']     ?? '',
                            ], static function ($v) { return $v !== '' && $v !== null; });
                            foreach ($addressParts as $line) {
                                echo e($line) . '<br>';
                            }

                            if (!empty($comp_data['RegAddress_Postcode'])):
                                $pcodeUrl = $dataOps->pcode2string($comp_data['RegAddress_Postcode']);
                            ?>
                                <a href="/postalcode/postcode/<?php echo eUrl($pcodeUrl); ?>">
                                    <?php echo e($comp_data['RegAddress_Postcode']); ?>
                                </a><br>
                            <?php endif; ?>

                            <?php if (!empty($comp_data['RegAddressCountry'])): ?>
                                <?php echo e($comp_data['RegAddressCountry']); ?>
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($comp_data['RegAddress_Postcode'])): ?>
                            <p>
                                <a class="btn btn-outline"
                                   href="https://www.google.com/maps/place/<?php echo eAttr(rawurlencode((string) $comp_data['RegAddress_Postcode'])); ?>"
                                   target="_blank" rel="noopener">
                                    <i class="fa fa-map-marker" aria-hidden="true"></i> View on Google Maps
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="panel">
                        <table class="kv-table">
                            <tr>
                                <td>Company Type</td>
                                <td><?php echo e($comp_data['CompanyCategory'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <td>Incorporated</td>
                                <td><?php echo e($comp_data['IncorporationDate'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td>
                                    <?php
                                    $status = (string) ($comp_data['CompanyStatus'] ?? '');
                                    $badgeClass = strcasecmp($status, 'Active') === 0 ? 'badge--success' : 'badge--danger';
                                    ?>
                                    <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($status); ?></span>
                                </td>
                            </tr>
                            <?php foreach (['SICCode_SicText_1','SICCode_SicText_2','SICCode_SicText_3','SICCode_SicText_4'] as $key): ?>
                                <?php if (!empty($comp_data[$key])): ?>
                                    <tr>
                                        <td>SIC</td>
                                        <td><?php echo e($comp_data[$key]); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <?php
                $prevNames = [];
                for ($i = 1; $i <= 10; $i++) {
                    $nameKey = "PreviousName_{$i}_CompanyName";
                    $dateKey = "PreviousName_{$i}_CONDATE";
                    if (!empty($comp_data[$nameKey])) {
                        $prevNames[] = [$comp_data[$nameKey], $comp_data[$dateKey] ?? ''];
                    }
                }
                if ($prevNames):
                ?>
                    <div class="mt-4">
                        <h3>Previous Names</h3>
                        <ul class="link-grid">
                            <?php foreach ($prevNames as [$name, $date]): ?>
                                <li><?php echo e($name); ?> <span class="text-muted">(<?php echo e($date); ?>)</span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </article>

            <!-- ======================================================== Financials ======================================================== -->
            <article id="financial" class="tabs__panel" role="tabpanel">
                <p class="text-muted">
                    Press Run Financial Analysis to load a comparison of the latest accounts.
                    You will see the main figures for up to the last three years, with one column for each year.
                    The first run can take a minute.
                </p>
                <p>
                    <button type="button"
                            class="btn btn-primary"
                            id="run-financial-analysis"
                            data-company="<?php echo eAttr($companyNumber); ?>">
                        Run Financial Analysis
                    </button>
                </p>
                <div id="financial-analysis-result" aria-live="polite"></div>
            </article>

            <!-- ======================================================== Shareholders ======================================================== -->
            <article id="shareholders" class="tabs__panel" role="tabpanel">
                <p class="text-muted">
                    Press Find shareholders to load the current shareholdings.
                    You will see how many shares the company has issued, and each owner’s name, number of shares and percentage.
                    A list already saved for this company appears straight away.
                </p>
                <p>
                    <button type="button"
                            class="btn btn-primary"
                            id="find-shareholders"
                            data-company="<?php echo eAttr($companyNumber); ?>">
                        Find shareholders
                    </button>
                </p>
                <div id="shareholders-result" aria-live="polite"></div>
            </article>

            <!-- ======================================================== Filing History ======================================================== -->
            <article id="filing-history" class="tabs__panel" role="tabpanel">
                <p class="text-muted">Documents returned by the Companies House filing-history API.</p>
                <?php if ($fdata === []): ?>
                    <p>No filing history is available from Companies House for this company.</p>
                <?php else: ?>
                <ul class="record-list">
                    <?php foreach ($fdata as $data): ?>
                        <li class="record">
                            <div class="record__title">
                                <?php
                                $href = 'https://find-and-update.company-information.service.gov.uk'
                                    . (string) ($data['link_self'] ?? '')
                                    . '/document?format=pdf&download=0';
                                ?>
                                <a href="<?php echo eAttr($href); ?>" target="_blank" rel="noopener nofollow">
                                    <?php echo e($data['type'] ?? 'Filing'); ?>
                                </a>
                                <span class="record__meta">
                                    <?php
                                    $filedOn = '';
                                    if (!empty($data['date'])) {
                                        $filedAt = date_create((string) $data['date']);
                                        $filedOn = $filedAt instanceof DateTimeInterface
                                            ? $filedAt->format('d/m/Y')
                                            : (string) $data['date'];
                                    }
                                    echo e($filedOn);
                                    ?>
                                </span>
                            </div>
                            <div class="record__body">
                                <strong><?php echo e(ucwords(str_replace(['-', ','], [' ', ', '], (string) ($data['description'] ?? '')))); ?></strong>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </article>
        </div>
    </div>
</section>
