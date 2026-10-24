<?php
class TreeRenderer
{
    // Alur: susun baris node datar dari DB jadi HTML pohon
    public static function render(array $rows): string
    {
        if (empty($rows)) {
            return '<p class="text-muted mb-0">Belum ada pohon keputusan untuk ditampilkan.</p>';
        }

        $byId = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            $byId[$r['id']] = $r;
        }

        $rootId = null;
        foreach ($byId as $id => $r) {
            if ($r['parent_id'] === null) {
                $rootId = $id;
            } else {
                $byId[$r['parent_id']]['children'][$r['node_side']] = $id;
            }
        }

        if ($rootId === null) {
            return '<p class="text-muted mb-0">Struktur pohon tidak valid.</p>';
        }

        $html = '<div class="cart-tree-wrapper"><div class="cart-tree"><ul>';
        $html .= self::renderNode($byId, $rootId, true, null);
        $html .= '</ul></div></div>';

        $html .= '<div class="tree-legend">'
            . '<span><span class="legend-swatch" style="background:#eafaf1;border:1px solid #34c38f;"></span>Daun: Layak</span>'
            . '<span><span class="legend-swatch" style="background:#fdecec;border:1px solid #e35d5d;"></span>Daun: Tidak Layak</span>'
            . '</div>';

        return $html;
    }

    // Render satu node & rekursi ke anaknya
    private static function renderNode(array $byId, int $id, bool $isRoot, ?string $branchLabel): string
    {
        $node = $byId[$id];
        $isLeaf = (bool)$node['is_leaf'];

        $distText = 'Layak: ' . (int)$node['layak_count'] . ' | Tidak Layak: ' . (int)$node['tidak_layak_count'];
        $giniText = 'Gini(t): ' . number_format((float)$node['gini'], 4) . ' &middot; Gini_split: ' . number_format((float)$node['gini_split'], 4);

        if ($isLeaf) {
            $cls = $node['predicted_class'] === 'Layak' ? 'tn-layak' : 'tn-tidak-layak';
            $conditionHtml = '<i class="bi bi-flag-fill"></i> ' . htmlspecialchars($node['predicted_class'], ENT_QUOTES, 'UTF-8')
                . ' (' . number_format((float)$node['confidence'], 1) . '%)';
            $box = '<div class="tree-node tn-leaf ' . $cls . '">'
                . '<div class="tn-condition">' . $conditionHtml . '</div>'
                . '<div class="tn-dist">' . $distText . ' &middot; n=' . (int)$node['samples'] . '</div>'
                . '</div>';
        } else {
            $conditionHtml = htmlspecialchars(
                self::conditionLabel($node['feature_label'], $node['split_type'], $node['split_value']),
                ENT_QUOTES, 'UTF-8'
            );
            $box = '<div class="tree-node' . ($isRoot ? ' tn-root' : '') . '">'
                . '<div class="tn-condition">' . $conditionHtml . '</div>'
                . '<div class="tn-gini">' . $giniText . '</div>'
                . '<div class="tn-dist">' . $distText . ' &middot; n=' . (int)$node['samples'] . '</div>'
                . '</div>';
        }

        $branchHtml = $branchLabel
            ? '<div class="mb-1"><span class="badge bg-secondary">' . htmlspecialchars($branchLabel, ENT_QUOTES, 'UTF-8') . '</span></div>'
            : '';

        $html = '<li>' . $branchHtml . $box;

        if (!$isLeaf && isset($node['children']['left'], $node['children']['right'])) {
            $html .= '<ul>';
            $html .= self::renderNode($byId, $node['children']['left'], false, 'Ya');
            $html .= self::renderNode($byId, $node['children']['right'], false, 'Tidak');
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }

    private static function conditionLabel(string $featureLabel, string $splitType, string $splitValue): string
    {
        if ($splitType === 'numeric') {
            $val = number_format((float)$splitValue, 0, ',', '.');
            return $featureLabel . ' ≤ ' . $val . ' ?';
        }
        $cats = explode('||', $splitValue);
        return $featureLabel . ' ∈ {' . implode(', ', $cats) . '} ?';
    }
}
