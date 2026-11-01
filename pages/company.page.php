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

                        <?php
                        $companiesHouseNumber = rawurlencode((string) $companyNumber);
                        $companiesHouseCompany = 'https://find-and-update.company-information.service.gov.uk/company/' . $companiesHouseNumber;
                        ?>
                        <p class="btn-row">
                            <a class="btn btn-outline"
                               href="<?php echo eAttr($companiesHouseCompany); ?>"
                               target="_blank" rel="noopener">
                                <i class="fa fa-building" aria-hidden="true"></i> Companies House
                            </a>
                            <a class="btn btn-outline"
                               href="<?php echo eAttr($companiesHouseCompany . '/officers'); ?>"
                               target="_blank" rel="noopener">
                                <i class="fa fa-users" aria-hidden="true"></i> People
                            </a>
                        </p>
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
                <?php
                $financialUpdated = is_array($financialSaved) ? trim((string) ($financialSaved['updated'] ?? '')) : '';
                ?>
                <p class="text-muted" id="financial-analysis-intro">
                    <?php if ($financialUpdated !== ''): ?>
                        Last updated on <?php echo e($financialUpdated); ?>.
                        <?php if ($canRefreshAnalysis): ?>
                            Press Refresh to load the figures again.
                            You will see the main figures for up to the last three years, with one column for each year.
                        <?php endif; ?>
                    <?php elseif ($canRefreshAnalysis): ?>
                        Press Run Financial Analysis to load a comparison of the latest accounts.
                        You will see the main figures for up to the last three years, with one column for each year.
                        The first run can take a minute.
                    <?php else: ?>
                        No saved accounts analysis is available for this company.
                    <?php endif; ?>
                </p>
                <?php if ($canRefreshAnalysis): ?>
                    <p>
                        <button type="button"
                                class="btn btn-primary"
                                id="run-financial-analysis"
                                data-company="<?php echo eAttr($companyNumber); ?>">
                            <?php echo $financialUpdated !== '' ? 'Refresh' : 'Run Financial Analysis'; ?>
                        </button>
                    </p>
                <?php endif; ?>
                <div id="financial-analysis-result" aria-live="polite">
                    <?php if (is_array($financialSaved)): ?>
                        <p><?php echo e($financialSaved['summary'] ?? ''); ?></p>
                        <?php
                        $periods = is_array($financialSaved['periods'] ?? null) ? $financialSaved['periods'] : [];
                        $figureRows = is_array($financialSaved['rows'] ?? null) ? $financialSaved['rows'] : [];
                        ?>
                        <?php if ($periods !== [] && $figureRows !== []): ?>
                            <div class="table-scroll">
                                <table class="data-table financial-analysis">
                                    <thead>
                                        <tr>
                                            <th scope="col">Figure</th>
                                            <?php foreach ($periods as $period): ?>
                                                <th scope="col" class="num">
                                                    <?php echo e($period['title'] ?? 'Annual accounts'); ?>
                                                    <?php if (!empty($period['accounts'])): ?>
                                                        <span class="financial-analysis__sub"><?php echo e($period['accounts']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($period['filed'])): ?>
                                                        <span class="financial-analysis__filed"><?php echo e($period['filed']); ?></span>
                                                    <?php endif; ?>
                                                    <?php
                                                    $filingUrl = (string) ($period['url'] ?? '');
                                                    $filingHost = strtolower((string) parse_url($filingUrl, PHP_URL_HOST));
                                                    $filingScheme = strtolower((string) parse_url($filingUrl, PHP_URL_SCHEME));
                                                    if ($filingScheme === 'https' && $filingHost === 'find-and-update.company-information.service.gov.uk'):
                                                    ?>
                                                        <a class="financial-analysis__link" href="<?php echo eAttr($filingUrl); ?>" target="_blank" rel="noopener nofollow">View filing</a>
                                                    <?php endif; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($figureRows as $figure): ?>
                                            <tr>
                                                <th scope="row"><?php echo e($figure['label'] ?? ''); ?></th>
                                                <?php
                                                $values = is_array($figure['values'] ?? null) ? $figure['values'] : [];
                                                foreach (array_keys($periods) as $index):
                                                    $value = $values[$index] ?? '';
                                                ?>
                                                    <td class="num"><?php echo e($value !== '' && $value !== null ? $value : '—'); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>

            <!-- ======================================================== Shareholders ======================================================== -->
            <article id="shareholders" class="tabs__panel" role="tabpanel">
                <?php
                $shareholderUpdated = is_array($shareholderSaved) ? trim((string) ($shareholderSaved['updated'] ?? '')) : '';
                ?>
                <p class="text-muted" id="shareholders-intro">
                    <?php if ($shareholderUpdated !== ''): ?>
                        Last updated on <?php echo e($shareholderUpdated); ?>.
                        <?php if ($canRefreshAnalysis): ?>
                            Press Refresh to read the shareholdings again.
                            You will see how many shares the company has issued, and each owner’s name, number of shares and percentage.
                        <?php endif; ?>
                    <?php elseif ($canRefreshAnalysis): ?>
                        Press Find shareholders to load the current shareholdings.
                        You will see how many shares the company has issued, and each owner’s name, number of shares and percentage.
                        The first run can take a minute.
                    <?php else: ?>
                        No saved shareholding is available for this company.
                    <?php endif; ?>
                </p>
                <?php if ($canRefreshAnalysis): ?>
                    <p>
                        <button type="button"
                                class="btn btn-primary"
                                id="find-shareholders"
                                data-company="<?php echo eAttr($companyNumber); ?>">
                            <?php echo $shareholderUpdated !== '' ? 'Refresh' : 'Find shareholders'; ?>
                        </button>
                    </p>
                <?php endif; ?>
                <div id="shareholders-result" aria-live="polite">
                    <?php if (is_array($shareholderSaved)): ?>
                        <p><?php echo e($shareholderSaved['summary'] ?? ''); ?></p>
                        <?php if (!empty($shareholderSaved['shares_issued'])): ?>
                            <p>Shares issued: <?php echo e($shareholderSaved['shares_issued']); ?></p>
                        <?php endif; ?>
                        <?php $holders = is_array($shareholderSaved['holders'] ?? null) ? $shareholderSaved['holders'] : []; ?>
                        <?php if ($holders !== []): ?>
                            <div class="table-scroll">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Shareholder</th>
                                            <th scope="col">Shares</th>
                                            <th scope="col">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($holders as $holder): ?>
                                            <tr>
                                                <th scope="row"><?php echo e($holder['name'] ?? ''); ?></th>
                                                <td><?php
                                                    $shares = trim((string) ($holder['shares'] ?? '') . ' ' . (string) ($holder['share_class'] ?? ''));
                                                    echo e($shares !== '' ? $shares : '—');
                                                ?></td>
                                                <td><?php echo e(($holder['percentage'] ?? '') !== '' ? $holder['percentage'] : '—'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>

            <!-- ======================================================== Filing History ======================================================== -->
            <article id="filing-history" class="tabs__panel" role="tabpanel">
                <p class="text-muted">Documents filed at Companies House.</p>
                <?php if ($fdata === []): ?>
                    <p>No filing history is available for this company.</p>
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
