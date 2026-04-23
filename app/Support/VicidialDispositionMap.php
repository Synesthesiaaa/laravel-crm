<?php

namespace App\Support;

/**
 * Bidirectional mapping between CRM lead / disposition codes and ViciDial status strings.
 */
class VicidialDispositionMap
{
    /**
     * CRM disposition / lead status code => ViciDial status (write-back).
     *
     * @return array<string, string>
     */
    public static function crmToVicidial(): array
    {
        return config('vicidial.disposition_map', []);
    }

    /**
     * ViciDial list / dialer status => CRM `leads.status` (and disposition_code on inbound rows).
     *
     * @return array<string, string>
     */
    public static function vicidialToCrm(): array
    {
        return config('vicidial.vicidial_to_crm_status', []);
    }

    public static function mapCrmToVicidial(string $crmCode): string
    {
        $map = self::crmToVicidial();

        return $map[$crmCode] ?? $crmCode;
    }

    public static function mapVicidialToCrm(string $viciCode): string
    {
        $v = strtoupper(trim($viciCode));
        $inverse = self::vicidialToCrm();
        if (isset($inverse[$v])) {
            return $inverse[$v];
        }

        // Fallback: invert disposition_map when value matches uniquely
        foreach (self::crmToVicidial() as $crm => $vici) {
            if (strtoupper((string) $vici) === $v) {
                return $crm;
            }
        }

        return $v;
    }
}
