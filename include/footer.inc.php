<?php
$sectorGroups = [
    [
        ['a', 'agriculture-forestry-fishing', 'Agriculture, Forestry & Fishing'],
        ['b', 'mining-quarrying',             'Mining & Quarrying'],
        ['c', 'manufacturing',                'Manufacturing'],
        ['d', 'electricity-gas-steam-air-conditioning-supply', 'Electricity, Gas & Steam'],
        ['e', 'water-supply-sewerage-waste-management-remediation-activities', 'Water & Waste'],
        ['f', 'construction',                 'Construction'],
        ['g', 'wholesale-retail-trade-repair-motor-vehicles-motorcycles', 'Wholesale & Retail'],
    ],
    [
        ['h', 'transportation-storage',                     'Transportation & Storage'],
        ['i', 'accommodation-food-service-activities',      'Accommodation & Food'],
        ['j', 'information-communication',                  'Information & Communication'],
        ['k', 'financial-insurance-activities',             'Financial & Insurance'],
        ['l', 'real-estate-activities',                     'Real Estate'],
        ['m', 'professional-scientific-technical-activities','Professional & Scientific'],
        ['n', 'administrative-support-service-activities',  'Administrative Support'],
    ],
    [
        ['o', 'public-administration-defence-compulsory-social-security', 'Public Administration'],
        ['p', 'education',                                                'Education'],
        ['q', 'human-health-social-work-activities',                      'Health & Social Work'],
        ['r', 'arts-entertainment-recreation',                            'Arts & Entertainment'],
        ['s', 'other-service-activities',                                 'Other Services'],
        ['t', 'activities-households',                                    'Household Activities'],
        ['u', 'activities-extraterritorial-organisations-bodies',         'Extraterritorial'],
    ],
];
?>
<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="site-footer__grid">
            <div>
                <h4>Data Index</h4>
                <p>UK company data indexed and accessible. Find information about UK registered
                   companies, validate VAT numbers, and explore open data at postcode level.</p>
                <ul class="social-list mt-2" aria-label="Social links">
                    <li>
                        <a href="https://www.twitter.com/dataindexonline" target="_blank" rel="noopener"
                           aria-label="Twitter">
                            <i class="fa fa-twitter" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li>
                        <a href="https://www.linkedin.com/company/dataindex" target="_blank" rel="noopener"
                           aria-label="LinkedIn">
                            <i class="fa fa-linkedin" aria-hidden="true"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <?php foreach ($sectorGroups as $i => $group): ?>
                <div>
                    <h4><?php echo $i === 0 ? 'Industries A–G' : ($i === 1 ? 'Industries H–N' : 'Industries O–U'); ?></h4>
                    <ul>
                        <?php foreach ($group as [$code, $slug, $label]): ?>
                            <li>
                                <a href="/industry/sector/<?php echo e($code); ?>/<?php echo e($slug); ?>/">
                                    <?php echo e($label); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="site-footer__bottom">
            <span>&copy; <?php echo date('Y'); ?> Data Index. All rights reserved.</span>
            <span>
                <a href="/">www.dataindex.online</a>
            </span>
        </div>
    </div>
</footer>
