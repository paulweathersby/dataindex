<?php $pcodeDisplay = $pcode_data['postcode'] ?? ''; ?>
<section class="page-header">
    <div class="container">
        <h1>
            Postcode <?php echo e($pcodeDisplay); ?>
        </h1>
        <p class="breadcrumbs">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <a href="/postcode-data">Postcode Data</a>
            <span aria-hidden="true">›</span>
            <?php echo e($pcode_data['location'] ?? ''); ?>
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid grid-2" style="gap:2rem;">
            <div class="panel">
                <h3 class="mt-0">Ward data</h3>
                <table class="kv-table">
                    <?php
                    $rows = [
                        'Postcode'     => $pcode_data['postcode']     ?? '',
                        'Location'     => $pcode_data['location']     ?? '',
                        'Country'      => $pcode_data['country']      ?? '',
                        'Country Code' => $pcode_data['country_code'] ?? '',
                        'County'       => $pcode_data['county']       ?? '',
                        'Ward'         => $pcode_data['ward']         ?? '',
                        'Ward Code'    => $pcode_data['code']         ?? '',
                        'Latitude'     => $pcode_data['latitude']     ?? '',
                        'Longitude'    => $pcode_data['longitude']    ?? '',
                    ];
                    foreach ($rows as $label => $value):
                    ?>
                        <tr>
                            <td><?php echo e($label); ?></td>
                            <td><?php echo e($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <?php if (!empty($pcode_data['latitude']) && !empty($pcode_data['longitude'])):
                $lat = (float) $pcode_data['latitude'];
                $lng = (float) $pcode_data['longitude'];
            ?>
                <div>
                    <iframe class="map-embed"
                            src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo eAttr($lng - 0.01); ?>%2C<?php echo eAttr($lat - 0.01); ?>%2C<?php echo eAttr($lng + 0.01); ?>%2C<?php echo eAttr($lat + 0.01); ?>&amp;layer=mapnik&amp;marker=<?php echo eAttr($lat); ?>%2C<?php echo eAttr($lng); ?>"
                            title="Map of <?php echo eAttr($pcodeDisplay); ?>"
                            loading="lazy"
                            referrerpolicy="no-referrer"></iframe>
                    <p class="mt-2 text-muted" style="font-size:.85rem;">
                        <a href="https://www.openstreetmap.org/#map=16/<?php echo eAttr($lat); ?>/<?php echo eAttr($lng); ?>"
                           target="_blank" rel="noopener">View larger map</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <h3 class="mt-4">Properties sold in <?php echo e($pcodeDisplay); ?></h3>
        <p class="text-muted">Price paid data from HM Land Registry, newest sale first.</p>
        <?php
        $propertyTypes = [
            'D' => 'Detached',
            'S' => 'Semi-detached',
            'T' => 'Terraced',
            'F' => 'Flat',
            'O' => 'Other',
        ];
        $tenures = [
            'F' => 'Freehold',
            'L' => 'Leasehold',
        ];
        $sales = $price_query->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if ($sales === []): ?>
            <p>No sales are recorded for this postcode.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table price-paid">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="num">Price</th>
                            <th>Address</th>
                            <th>Property</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale):
                            $soldOn = strtotime((string) ($sale['date_of_transfer'] ?? ''));
                            $addressParts = [];
                            foreach (['saon', 'paon', 'street', 'locality', 'town_city', 'district', 'county'] as $part) {
                                $value = trim((string) ($sale[$part] ?? ''));
                                if ($value === '') {
                                    continue;
                                }
                                $previous = $addressParts[count($addressParts) - 1] ?? '';
                                if ($previous !== '' && strcasecmp($previous, $value) === 0) {
                                    continue;
                                }
                                $addressParts[] = $value;
                            }
                            $details = array_filter([
                                $propertyTypes[$sale['property_type'] ?? ''] ?? '',
                                $tenures[$sale['duration'] ?? ''] ?? '',
                                (($sale['old_new'] ?? '') === 'Y') ? 'New build' : '',
                                (($sale['ppd_category_type'] ?? '') === 'B') ? 'Additional price paid' : '',
                            ], static function ($value) { return $value !== ''; });
                        ?>
                            <tr>
                                <td><?php echo e($soldOn ? date('j F Y', $soldOn) : ''); ?></td>
                                <td class="num"><?php echo e('£' . number_format((int) ($sale['price'] ?? 0))); ?></td>
                                <td><?php echo e(implode(', ', $addressParts)); ?></td>
                                <td><?php echo e(implode(', ', $details)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="mt-4">Registered companies in <?php echo e($pcodeDisplay); ?></h3>
        <ul class="record-list">
            <?php while ($comp_data = $comp_query->fetch(PDO::FETCH_ASSOC)): ?>
                <li class="record">
                    <div class="record__title">
                        <a href="/company/number/<?php echo eUrl($comp_data['CompanyNumber']); ?>">
                            <?php echo e($comp_data['CompanyName']); ?>
                        </a>
                        <span class="record__meta"><?php echo e($comp_data['CompanyNumber']); ?></span>
                    </div>
                    <div class="record__body">
                        <?php
                        $addr = array_filter([
                            $comp_data['RegAddress_CareOf']      ?? '',
                            $comp_data['RegAddress_POBox']       ?? '',
                            $comp_data['RegAddress_AddressLine1'] ?? '',
                            $comp_data['RegAddress_AddressLine2'] ?? '',
                            $comp_data['RegAddress_Town']        ?? '',
                            $comp_data['RegAddress_County']      ?? '',
                            $comp_data['RegAddress_Postcode']    ?? '',
                            $comp_data['RegAddressCountry']      ?? '',
                        ], static function ($v) { return $v !== '' && $v !== null; });
                        echo e(implode(', ', $addr));
                        ?>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>

        <h3 class="mt-4">VAT registered businesses in <?php echo e($pcodeDisplay); ?></h3>
        <ul class="record-list">
            <?php while ($vat_data = $vat_query->fetch(PDO::FETCH_ASSOC)): ?>
                <li class="record">
                    <div class="record__title">
                        <?php echo e($vat_data['company_name']); ?>
                        <span class="record__meta">GB<?php echo e($vat_data['vat_number']); ?></span>
                    </div>
                    <div class="record__body">
                        <?php
                        $addr = array_filter([
                            $vat_data['address1']    ?? '',
                            $vat_data['address2']    ?? '',
                            $vat_data['address3']    ?? '',
                            $vat_data['address4']    ?? '',
                            $vat_data['address5']    ?? '',
                            $vat_data['postcode']    ?? '',
                            $vat_data['country_code'] ?? '',
                        ], static function ($v) { return $v !== '' && $v !== null; });
                        echo e(implode(', ', $addr));
                        ?>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>

    </div>
</section>
