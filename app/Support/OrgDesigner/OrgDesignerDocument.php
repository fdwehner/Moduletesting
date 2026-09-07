<?php

namespace App\Support\OrgDesigner;

final class OrgDesignerDocument
{
    public const TOPOLOGIES = ['stream-aligned', 'enabling', 'platform', 'complicated-subsystem'];

    public const FALLBACK_GROUP = 99;

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'iltAreas' => [],
            'currentILT' => null,
            'viewMode' => 'team',
            'bigPictureOrder' => [],
            'bigPictureRows' => [[]],
            'zoom' => 1.0,
            'savedAt' => null,
            'dirty' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'sheetName' => 'New Baseline File - Option C',
            'colWidth' => 240,
            'roleColors' => self::defaultRoleColors(),
            'locations' => self::defaultLocations(),
            'topology' => self::defaultTopology(),
        ];
    }

    /**
     * @return list<array{pattern: string, color: string, group: int}>
     */
    public static function defaultRoleColors(): array
    {
        return [
            ['pattern' => 'Team Lead', 'color' => '#f4b7b7', 'group' => 1],
            ['pattern' => 'Chief Product Owner', 'color' => '#ffd966', 'group' => 2],
            ['pattern' => 'Product Owner Lead', 'color' => '#ffd966', 'group' => 2],
            ['pattern' => 'Product Owner', 'color' => '#ffd966', 'group' => 2],
            ['pattern' => 'Chief Engineer', 'color' => '#fff6b3', 'group' => 3],
            ['pattern' => 'DevOps / IT Infrastructure Engineer', 'color' => '#fff6b3', 'group' => 3],
            ['pattern' => 'IT Support Specialist', 'color' => '#fff6b3', 'group' => 3],
            ['pattern' => 'SW Developer / Engineer', 'color' => '#fff6b3', 'group' => 3],
            ['pattern' => 'IT Security Consultant', 'color' => '#fff6b3', 'group' => 3],
            ['pattern' => 'Chief Analyst', 'color' => '#6fa8dc', 'group' => 4],
            ['pattern' => 'Digital Business Analyst', 'color' => '#6fa8dc', 'group' => 4],
            ['pattern' => 'SW Quality Assurance Engineer', 'color' => '#6fa8dc', 'group' => 4],
            ['pattern' => 'SW Quality Assurance', 'color' => '#6fa8dc', 'group' => 4],
            ['pattern' => 'UI/UX Designer / Consultant', 'color' => '#6fa8dc', 'group' => 4],
            ['pattern' => 'Chief Project Manager / Agile Coach', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'Project Manager / Agile Coach Lead', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'Project Manager', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'IT Project Manager', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'IT / Software Project Manager', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'Agile Coach / Scrum Master', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'IT Scrum Master', 'color' => '#b7b7b7', 'group' => 5],
            ['pattern' => 'Chief Architect', 'color' => '#93c47d', 'group' => 6],
            ['pattern' => 'Architect', 'color' => '#93c47d', 'group' => 6],
            ['pattern' => 'IT Architect', 'color' => '#93c47d', 'group' => 6],
            ['pattern' => 'Chief Cohort / Function', 'color' => '#f4b7b7', 'group' => 7],
            ['pattern' => 'Cohort / Functional Lead', 'color' => '#f4b7b7', 'group' => 7],
            ['pattern' => 'Head of IT Vertical / Horizontal', 'color' => '#f5a623', 'group' => 7],
            ['pattern' => 'Head of SV', 'color' => '#f5a623', 'group' => 7],
            ['pattern' => 'Head of HRIT', 'color' => '#f5a623', 'group' => 7],
        ];
    }

    /**
     * @return list<array{code: string, flag: string}>
     */
    public static function defaultLocations(): array
    {
        return [
            ['code' => 'KL', 'flag' => "\u{1F1F2}\u{1F1FE}"],
            ['code' => 'Buchs', 'flag' => "\u{1F1E8}\u{1F1ED}"],
            ['code' => 'Schaan', 'flag' => "\u{1F1F1}\u{1F1EE}"],
            ['code' => 'Kaufering', 'flag' => "\u{1F1E9}\u{1F1EA}"],
            ['code' => 'HNA', 'flag' => "\u{1F1FA}\u{1F1F8}"],
            ['code' => 'Tulsa', 'flag' => "\u{1F1FA}\u{1F1F8}"],
            ['code' => 'Berkel', 'flag' => "\u{1F1F3}\u{1F1F1}"],
        ];
    }

    /**
     * @return list<array{key: string, color: string}>
     */
    public static function defaultTopology(): array
    {
        return [
            ['key' => 'stream-aligned', 'color' => '#E6E0D5'],
            ['key' => 'enabling', 'color' => '#83D4A5'],
            ['key' => 'platform', 'color' => '#D2051E'],
            ['key' => 'complicated-subsystem', 'color' => '#edc948'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function knownRoles(): array
    {
        return array_values(array_unique(array_column(self::defaultRoleColors(), 'pattern')));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function sanitizeState(array $state): array
    {
        $areas = [];
        $rawAreas = is_array($state['iltAreas'] ?? null) ? $state['iltAreas'] : [];

        foreach ($rawAreas as $key => $area) {
            if (! is_array($area)) {
                continue;
            }
            $name = self::str($area['name'] ?? $key, 120);
            if ($name === '') {
                continue;
            }
            $areas[$name] = [
                'name' => $name,
                'head' => self::sanitizeHead($area['head'] ?? null),
                'teams' => self::sanitizeTeams($area['teams'] ?? []),
            ];
        }

        $current = isset($state['currentILT']) ? self::str((string) $state['currentILT'], 120) : null;
        if ($current !== null && $current !== '' && ! array_key_exists($current, $areas)) {
            $current = array_key_first($areas);
        }
        if ($current === '') {
            $current = null;
        }

        $order = [];
        foreach (is_array($state['bigPictureOrder'] ?? null) ? $state['bigPictureOrder'] : [] as $name) {
            $name = self::str((string) $name, 120);
            if ($name !== '' && isset($areas[$name]) && ! in_array($name, $order, true)) {
                $order[] = $name;
            }
        }

        $rows = [];
        $seen = [];
        foreach (is_array($state['bigPictureRows'] ?? null) ? $state['bigPictureRows'] : [[]] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($row as $name) {
                $name = self::str((string) $name, 120);
                if ($name !== '' && isset($areas[$name]) && ! isset($seen[$name])) {
                    $clean[] = $name;
                    $seen[$name] = true;
                }
            }
            $rows[] = $clean;
        }
        if ($rows === []) {
            $rows = [[]];
        }
        foreach (array_keys($areas) as $name) {
            if (! isset($seen[$name])) {
                $rows[count($rows) - 1][] = $name;
            }
        }

        $zoom = (float) ($state['zoom'] ?? 1);
        $zoom = max(0.15, min(2.0, $zoom));

        $viewMode = ($state['viewMode'] ?? 'team') === 'bigpicture' ? 'bigpicture' : 'team';

        return [
            'iltAreas' => $areas,
            'currentILT' => $current,
            'viewMode' => $viewMode,
            'bigPictureOrder' => $order !== [] ? $order : array_keys($areas),
            'bigPictureRows' => $rows,
            'zoom' => $zoom,
            'savedAt' => now()->toIso8601String(),
            'dirty' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function sanitizeConfig(array $config): array
    {
        $defaults = self::defaultConfig();
        $roleColors = [];
        foreach (is_array($config['roleColors'] ?? null) ? $config['roleColors'] : $defaults['roleColors'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pattern = self::str($row['pattern'] ?? '', 120);
            if ($pattern === '') {
                continue;
            }
            $roleColors[] = [
                'pattern' => $pattern,
                'color' => self::hex($row['color'] ?? '#ffffff'),
                'group' => max(1, min(99, (int) ($row['group'] ?? self::FALLBACK_GROUP))),
            ];
        }

        $locations = [];
        foreach (is_array($config['locations'] ?? null) ? $config['locations'] : $defaults['locations'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = self::str($row['code'] ?? '', 80);
            if ($code === '') {
                continue;
            }
            $locations[] = [
                'code' => $code,
                'flag' => self::str($row['flag'] ?? '', 16),
            ];
        }

        $topology = [];
        $topoMap = [];
        foreach (is_array($config['topology'] ?? null) ? $config['topology'] : [] as $row) {
            if (is_array($row) && isset($row['key'])) {
                $topoMap[(string) $row['key']] = $row['color'] ?? null;
            }
        }
        foreach (self::defaultTopology() as $row) {
            $topology[] = [
                'key' => $row['key'],
                'color' => self::hex($topoMap[$row['key']] ?? $row['color']),
            ];
        }

        return [
            'sheetName' => self::str($config['sheetName'] ?? $defaults['sheetName'], 120) ?: $defaults['sheetName'],
            'colWidth' => max(180, min(400, (int) ($config['colWidth'] ?? 240))),
            'roleColors' => $roleColors !== [] ? $roleColors : $defaults['roleColors'],
            'locations' => $locations !== [] ? $locations : $defaults['locations'],
            'topology' => $topology,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function encodeForClient(array $state): array
    {
        $encoded = $state;
        if (($encoded['iltAreas'] ?? []) === []) {
            $encoded['iltAreas'] = new \stdClass;
        }

        return $encoded;
    }

    private static function sanitizeHead(mixed $head): ?array
    {
        if (! is_array($head)) {
            return null;
        }
        $role = self::str($head['role'] ?? '', 120);
        if ($role === '') {
            return null;
        }

        return [
            'role' => $role,
            'grade' => self::str($head['grade'] ?? '', 40),
            'intExt' => self::intExt($head['intExt'] ?? 'Internal'),
            'location' => self::str($head['location'] ?? '', 80),
            'notes' => self::str($head['notes'] ?? '', 2000),
        ];
    }

    /**
     * @param  mixed  $teams
     * @return list<array<string, mixed>>
     */
    private static function sanitizeTeams(mixed $teams): array
    {
        if (! is_array($teams)) {
            return [];
        }

        $clean = [];
        foreach ($teams as $team) {
            if (! is_array($team)) {
                continue;
            }
            $id = self::str($team['id'] ?? '', 80);
            if ($id === '') {
                $id = 'Team_'.bin2hex(random_bytes(4));
            }
            $topology = strtolower(self::str($team['topology'] ?? 'stream-aligned', 40));
            if (! in_array($topology, self::TOPOLOGIES, true)) {
                $topology = 'stream-aligned';
            }
            $products = [];
            foreach (is_array($team['products'] ?? null) ? $team['products'] : [] as $product) {
                $product = self::str((string) $product, 120);
                if ($product !== '') {
                    $products[] = $product;
                }
            }
            $clean[] = [
                'id' => $id,
                'name' => self::str($team['name'] ?? '', 120) ?: 'Untitled',
                'topology' => $topology,
                'products' => array_slice($products, 0, 3),
                'notes' => self::str($team['notes'] ?? '', 2000),
                'positions' => self::sanitizePositions($team['positions'] ?? []),
            ];
        }

        return $clean;
    }

    /**
     * @param  mixed  $positions
     * @return list<array<string, mixed>>
     */
    private static function sanitizePositions(mixed $positions): array
    {
        if (! is_array($positions)) {
            return [];
        }

        $clean = [];
        foreach ($positions as $position) {
            if (! is_array($position)) {
                continue;
            }
            $id = self::str($position['id'] ?? '', 80);
            if ($id === '') {
                $id = 'Pos_'.bin2hex(random_bytes(4));
            }
            $clean[] = [
                'id' => $id,
                'role' => self::str($position['role'] ?? '', 120),
                'grade' => self::str($position['grade'] ?? '', 40),
                'fte' => self::str($position['fte'] ?? '1', 20) ?: '1',
                'intExt' => self::intExt($position['intExt'] ?? 'Internal'),
                'location' => self::str($position['location'] ?? '', 80),
                'notes' => self::str($position['notes'] ?? '', 2000),
            ];
        }

        return $clean;
    }

    private static function intExt(mixed $value): string
    {
        $raw = strtolower(self::str((string) $value, 20));

        return str_starts_with($raw, 'ext') ? 'External' : 'Internal';
    }

    private static function hex(mixed $value): string
    {
        $raw = strtoupper(self::str((string) $value, 7));
        if (preg_match('/^#[0-9A-F]{6}$/', $raw) === 1) {
            return $raw;
        }

        return '#FFFFFF';
    }

    private static function str(mixed $value, int $max): string
    {
        $raw = trim(strip_tags((string) $value));
        if (mb_strlen($raw) > $max) {
            return mb_substr($raw, 0, $max);
        }

        return $raw;
    }
}
