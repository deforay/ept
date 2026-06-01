<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Algorithm registry. Adding a new variant: drop a new entry here, plus
 * a class under EptTestHarness\Aberrations and an expectations/<key>.php.
 */
final class Variants
{
    /** @return array<string, array{label:string, algoKey:string, schemeType:string, tierAware:bool, aberrations:class-string, expectations:string}> */
    public static function all(): array
    {
        $base = dirname(__DIR__);
        return [
            'vietnam' => [
                'label'        => 'Vietnam (NIHE)',
                'algoKey'      => 'vietnamNationalDtsAlgo',
                'schemeType'   => 'vietnam',
                'reportLayout' => 'vietnam',
                'tierAware'    => true,
                'aberrations'  => \EptTestHarness\Aberrations\Vietnam::class,
                'expectations' => $base . '/expectations/vietnam.php',
                // r_possibleresult rows seeded by migration 7.4.6 with display_context='none'.
                // Flip them to 'all' on provision so admin/participant dropdowns surface the
                // Vietnam-specific vocabulary (WR test result, IND test result, INC final).
                // Stashed previous values are restored on cleanup.
                'exposeResultCodes' => [
                    ['scheme' => 'dts', 'sub_group' => 'DTS_TEST',  'code' => 'WR'],
                    ['scheme' => 'dts', 'sub_group' => 'DTS_TEST',  'code' => 'IND'],
                    ['scheme' => 'dts', 'sub_group' => 'DTS_FINAL', 'code' => 'INC'],
                ],
                // Shipment-level flags so the response form renders the right shape:
                //   screeningTest='no'        — panel is 3-test (NOT screening-only)
                //   noOfTestsInPanel=3        — 3 test columns
                //   dtsTestPanelType='yes'    — participant can pick screening/confirmatory
                'shipmentAttributes' => [
                    'screeningTest'      => 'no',
                    'noOfTestsInPanel'   => 3,
                    'dtsTestPanelType'   => 'yes',
                ],
                // Global scheme_config.dts overrides applied on provision and
                // restored on cleanup. Vietnam restricts the algorithm dropdown
                // to vietnamNationalDtsAlgo only, and is qualitative — no
                // documentation score.
                'dtsConfig' => [
                    'allowedAlgorithms'   => 'vietnamNationalDtsAlgo',
                    'documentationScore'  => '0',
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        if (!isset($all[$key])) {
            throw new \RuntimeException("Unknown variant: $key");
        }
        return $all[$key];
    }
}
