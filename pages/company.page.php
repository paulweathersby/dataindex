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
                <li><a href="#people" role="tab">People</a></li>
                <li><a href="#financial" role="tab">Financials</a></li>
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

            <!-- ======================================================== People ======================================================== -->
            <article id="people" class="tabs__panel" role="tabpanel">
                <h3 class="mt-0">Persons of Significant Control</h3>
                <p class="text-muted">Declared owners and their nature of control.</p>

                <ul class="record-list">
                    <?php while ($pdata = $psc_query->fetch(PDO::FETCH_ASSOC)): ?>
                        <li class="record">
                            <div class="record__title">
                                <?php echo e($pdata['name']); ?>
                                <?php if (!empty($pdata['dob_month']) && !empty($pdata['dob_year'])): ?>
                                    <span class="record__meta">
                                        DOB <?php echo e($pdata['dob_month']); ?>/<?php echo e($pdata['dob_year']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="record__body">
                                <?php
                                $addr = array_filter([
                                    $pdata['premises']       ?? '',
                                    $pdata['address_line_1'] ?? '',
                                    $pdata['locality']       ?? '',
                                    $pdata['postal_code']    ?? '',
                                    $pdata['country']        ?? '',
                                ], static function ($v) { return $v !== '' && $v !== null; });
                                echo e(implode(', ', $addr));
                                ?>
                                <br>
                                <?php
                                $notified = !empty($pdata['notified_on'])
                                    ? date_format(date_create((string) $pdata['notified_on']), 'd/m/Y')
                                    : '';
                                ?>
                                <span class="text-muted">
                                    <?php echo e($notified); ?>
                                    — <?php echo e(ucwords(str_replace('-', ' ', (string) ($pdata['kind'] ?? '')))); ?>
                                </span><br>
                                <?php echo e(ucwords(str_replace(['-', ','], [' ', ', '], (string) ($pdata['natures_of_control'] ?? '')))); ?>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>

                <h3 class="mt-4">Officers</h3>
                <p class="text-muted">Directors, secretaries, and other appointed officers.</p>

                <ul class="record-list">
                    <?php foreach ($idata as $data): ?>
                        <li class="record">
                            <div class="record__title">
                                <?php
                                if (!empty($data['registration_number'])) {
                                    echo e($data['name'] . ' (' . $data['registration_number'] . ')');
                                } elseif (!empty($data['name']) && strpos((string) $data['name'], ',') !== false) {
                                    $parts = explode(',', (string) $data['name']);
                                    echo e(trim($parts[1] ?? '') . ' ' . ucfirst(strtolower(trim($parts[0] ?? ''))));
                                } else {
                                    echo e($data['name'] ?? '');
                                }
                                ?>
                                <?php if (!empty($data['dob_month']) && !empty($data['dob_year'])): ?>
                                    <span class="record__meta">
                                        DOB <?php echo e($data['dob_month']); ?>/<?php echo e($data['dob_year']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (empty($data['resigned_on'])): ?>
                                    <span class="badge badge--success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge--danger">Resigned</span>
                                <?php endif; ?>
                            </div>
                            <div class="record__body">
                                <?php
                                $role = ucwords(str_replace('-', ' ', (string) ($data['officer_role'] ?? '')));
                                $appointed = !empty($data['appointed_on']) ? date_format(date_create((string) $data['appointed_on']), 'd/m/Y') : '';
                                $end = !empty($data['resigned_on']) ? date_format(date_create((string) $data['resigned_on']), 'd/m/Y') : 'Present';
                                ?>
                                <strong><?php echo e($role); ?></strong>
                                <span class="text-muted">(<?php echo e($appointed); ?> – <?php echo e($end); ?>)</span><br>

                                <?php if (!empty($data['nationality'])): ?>
                                    <span class="text-muted">Nationality:</span> <?php echo e($data['nationality']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($data['country_of_residence'])): ?>
                                    <span class="text-muted">Country of residence:</span> <?php echo e($data['country_of_residence']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($data['occupation'])): ?>
                                    <span class="text-muted">Occupation:</span> <?php echo e($data['occupation']); ?><br>
                                <?php endif; ?>

                                <?php
                                $oAddr = array_filter([
                                    $data['premises']       ?? '',
                                    $data['address_line_1'] ?? '',
                                    $data['locality']       ?? '',
                                ], static function ($v) { return $v !== '' && $v !== null; });
                                if ($oAddr):
                                ?>
                                    <?php echo e(implode(', ', $oAddr)); ?>
                                <?php endif; ?>

                                <?php if (!empty($data['postal_code'])): ?>
                                    <?php if (($data['country_of_residence'] ?? '') === 'United Kingdom'): ?>
                                        <a href="/postalcode/postcode/<?php echo eUrl($dataOps->pcode2string($data['postal_code'])); ?>">
                                            <?php echo e($data['postal_code']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo e($data['postal_code']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($data['country'])): ?>
                                    <?php echo e($data['country']); ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <!-- ======================================================== Financials ======================================================== -->
            <article id="financial" class="tabs__panel" role="tabpanel">
                <div class="panel">
                    <table class="kv-table">
                        <tr>
                            <td>Account Reference Date</td>
                            <td><?php echo e(($comp_data['Accounts_AccountRefDay'] ?? '') . '/' . ($comp_data['Accounts_AccountRefMonth'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <td>Accounts Category</td>
                            <td><?php echo e(ucwords(strtolower((string) ($comp_data['Accounts_AccountCategory'] ?? '')))); ?></td>
                        </tr>
                        <tr>
                            <td>Latest Accounts</td>
                            <td><?php echo e($comp_data['Accounts_LastMadeUpDate'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td>Next Accounts Due</td>
                            <td><?php echo e($comp_data['Accounts_NextDueDate'] ?? ''); ?></td>
                        </tr>
                    </table>
                </div>

                <?php while ($fin_data = $fin_query->fetch(PDO::FETCH_ASSOC)): ?>
                    <h4 class="mt-4">Filed <?php echo e($fin_data['filed'] ?? ''); ?></h4>
                    <table class="data-table">
                        <tbody>
                            <?php
                            $rows = [
                                'Period End'                         => $fin_data['periodDate']                          ?? '',
                                'Filing Date'                        => $fin_data['filingDate']                          ?? '',
                                'Cash at Bank in Hand'               => $fin_data['CashBankOnHand']                      ?? '',
                                'Fixed Assets'                       => $fin_data['FixedAssets']                         ?? '',
                                'Current Assets'                     => $fin_data['CurrentAssets']                       ?? '',
                                'Creditors'                          => $fin_data['Creditors']                           ?? '',
                                'Net Current Assets / Liabilities'   => $fin_data['NetCurrentAssetsLiabilities']         ?? '',
                                'Total Assets Less Current Liab.'    => $fin_data['TotalAssetsLessCurrentLiabilities']   ?? '',
                                'Net Assets / Liabilities'           => $fin_data['NetAssetsLiabilities']                ?? '',
                                'Equity'                             => $fin_data['Equity']                              ?? '',
                                'Avg Employees During Period'        => $fin_data['AverageNumberEmployeesDuringPeriod']  ?? '',
                            ];
                            foreach ($rows as $label => $value):
                            ?>
                                <tr>
                                    <th><?php echo e($label); ?></th>
                                    <td><?php echo e($value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endwhile; ?>
            </article>

            <!-- ======================================================== Filing History ======================================================== -->
            <article id="filing-history" class="tabs__panel" role="tabpanel">
                <p class="text-muted">Documents filed by the company.</p>
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
                                    <?php echo e(!empty($data['date']) ? date_format(date_create((string) $data['date']), 'd/m/Y') : ''); ?>
                                </span>
                            </div>
                            <div class="record__body">
                                <strong><?php echo e(ucwords(str_replace(['-', ','], [' ', ', '], (string) ($data['description'] ?? '')))); ?></strong>
                                <?php foreach ([
                                    'Old Address'        => $data['old_address']        ?? '',
                                    'New Address'        => $data['new_address']        ?? '',
                                    'Officer Name'       => $data['officer_name']       ?? '',
                                ] as $label => $value): ?>
                                    <?php if ($value !== ''): ?>
                                        <br><span class="text-muted"><?php echo e($label); ?>:</span> <?php echo e($value); ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php foreach ([
                                    'Appointment Date' => $data['appointment_date'] ?? null,
                                    'Termination Date' => $data['termination_date'] ?? null,
                                    'Change Date'      => $data['change_date']      ?? null,
                                ] as $label => $value): ?>
                                    <?php if (!empty($value)): ?>
                                        <br><span class="text-muted"><?php echo e($label); ?>:</span>
                                        <?php echo e(date_format(date_create((string) $value), 'd/m/Y')); ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </div>
    </div>
</section>
