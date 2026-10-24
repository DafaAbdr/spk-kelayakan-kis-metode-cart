<?php
class CartTree
{
    // Konfigurasi atribut prediktor
    private array $features;

    // Nama kolom kelas target (Layak / Tidak Layak)
    private string $targetKey;

    private int $maxDepth;
    private int $minSamplesSplit;
    private int $minSamplesLeaf;

    // Struktur pohon hasil training
    public ?array $tree = null;

    private array $classes = [];

    public function __construct(array $features, string $targetKey, int $maxDepth = 4, int $minSamplesSplit = 4, int $minSamplesLeaf = 2)
    {
        $this->features = $features;
        $this->targetKey = $targetKey;
        $this->maxDepth = $maxDepth;
        $this->minSamplesSplit = $minSamplesSplit;
        $this->minSamplesLeaf = $minSamplesLeaf;
    }

    // Alur 1: latih pohon dari data training
    public function fit(array $data): array
    {
        $this->classes = array_values(array_unique(array_map(fn($r) => $r[$this->targetKey], $data)));
        sort($this->classes);
        $this->tree = $this->buildNode($data, 0);
        return $this->tree;
    }

    // Alur 2: hitung Gini Index (tingkat impurity) satu node
    private function gini(array $data): float
    {
        $n = count($data);
        if ($n === 0) return 0.0;

        $counts = $this->classCounts($data);
        $impurity = 1.0;
        foreach ($counts as $c) {
            $p = $c / $n;
            $impurity -= $p * $p;
        }
        return $impurity;
    }

    // Hitung jumlah data per kelas dalam satu node
    private function classCounts(array $data): array
    {
        $counts = [];
        foreach ($this->classes as $c) $counts[$c] = 0;
        foreach ($data as $row) {
            $label = $row[$this->targetKey];
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        return $counts;
    }

    // Tentukan kelas mayoritas & confidence node
    private function majorityClass(array $counts): array
    {
        arsort($counts);
        $label = array_key_first($counts);
        $total = array_sum($counts);
        $confidence = $total > 0 ? ($counts[$label] / $total) * 100 : 0;
        return [$label, round($confidence, 2)];
    }

    // Kriteria berhenti: node sudah satu kelas saja
    private function isPure(array $data): bool
    {
        if (count($data) === 0) return true;
        $first = $data[0][$this->targetKey];
        foreach ($data as $row) {
            if ($row[$this->targetKey] !== $first) return false;
        }
        return true;
    }

    // Gini Split: rata-rata Gini dua cabang, dibobot jumlah data
    private function giniSplit(array $left, array $right, int $nNode): float
    {
        $nL = count($left);
        $nR = count($right);
        if ($nNode === 0) return 1.0;

        $giniL = $this->gini($left);
        $giniR = $this->gini($right);

        return ($nL / $nNode) * $giniL + ($nR / $nNode) * $giniR;
    }

    // Alur 3: bangun pohon secara rekursif (binary recursive partitioning)
    private function buildNode(array $data, int $depth): array
    {
        $n = count($data);
        $counts = $this->classCounts($data);
        $giniNode = $this->gini($data);
        [$majority, $confidence] = $this->majorityClass($counts);

        $node = [
            'depth' => $depth,
            'samples' => $n,
            'gini' => round($giniNode, 4),
            'gini_split' => null,
            'counts' => $counts,
            'is_leaf' => true,
            'predicted_class' => $majority,
            'confidence' => $confidence,
            'feature' => null,
            'feature_label' => null,
            'split_type' => null,
            'split_value' => null,
            'left' => null,
            'right' => null,
        ];

        if ($this->isPure($data) || $depth >= $this->maxDepth || $n < $this->minSamplesSplit) {
            return $node;
        }

        $best = $this->bestSplit($data, $n, $giniNode);

        // Tidak ada split yang menurunkan Gini -> jadi daun
        if ($best === null || $best['gini_split'] >= $giniNode - 1e-9) {
            return $node;
        }

        $node['is_leaf'] = false;
        $node['feature'] = $best['feature'];
        $node['feature_label'] = $this->features[$best['feature']]['label'];
        $node['split_type'] = $best['type'];
        $node['split_value'] = $best['split_value'];
        $node['gini_split'] = round($best['gini_split'], 6);

        // Rekursi ke cabang kiri & kanan
        $node['left'] = $this->buildNode($best['left_data'], $depth + 1);
        $node['right'] = $this->buildNode($best['right_data'], $depth + 1);

        return $node;
    }

    // Alur 2b: cari atribut & titik split terbaik (Gini terkecil)
    private function bestSplit(array $data, int $nNode, float $parentGini): ?array
    {
        $best = null;
        $bestGiniSplit = $parentGini;

        foreach ($this->features as $name => $conf) {
            if ($conf['type'] === 'numeric') {
                $values = array_values(array_unique(array_map(fn($r) => (float)$r[$name], $data)));
                sort($values);
                for ($i = 0; $i < count($values) - 1; $i++) {
                    $threshold = ($values[$i] + $values[$i + 1]) / 2;
                    $left = array_values(array_filter($data, fn($r) => (float)$r[$name] <= $threshold));
                    $right = array_values(array_filter($data, fn($r) => (float)$r[$name] > $threshold));
                    if (count($left) < $this->minSamplesLeaf || count($right) < $this->minSamplesLeaf) continue;

                    $giniSplit = $this->giniSplit($left, $right, $nNode);
                    if ($giniSplit < $bestGiniSplit - 1e-9) {
                        $bestGiniSplit = $giniSplit;
                        $best = [
                            'feature' => $name,
                            'type' => 'numeric',
                            'split_value' => $threshold,
                            'left_data' => $left,
                            'right_data' => $right,
                            'gini_split' => $giniSplit,
                        ];
                    }
                }
            } else {
                $cats = array_values(array_unique(array_map(fn($r) => $r[$name], $data)));
                if (count($cats) < 2) continue;

                foreach ($this->nonTrivialSubsets($cats) as $subset) {
                    $left = array_values(array_filter($data, fn($r) => in_array($r[$name], $subset, true)));
                    $right = array_values(array_filter($data, fn($r) => !in_array($r[$name], $subset, true)));
                    if (count($left) < $this->minSamplesLeaf || count($right) < $this->minSamplesLeaf) continue;

                    $giniSplit = $this->giniSplit($left, $right, $nNode);
                    if ($giniSplit < $bestGiniSplit - 1e-9) {
                        $bestGiniSplit = $giniSplit;
                        $best = [
                            'feature' => $name,
                            'type' => 'categorical',
                            'split_value' => $subset,
                            'left_data' => $left,
                            'right_data' => $right,
                            'gini_split' => $giniSplit,
                        ];
                    }
                }
            }
        }

        return $best;
    }

    // Semua kombinasi subset kategori (untuk split atribut kategorik)
    private function nonTrivialSubsets(array $cats): array
    {
        $n = count($cats);
        $subsets = [];
        $total = 1 << $n;
        for ($mask = 1; $mask < $total - 1; $mask++) {
            $subset = [];
            for ($i = 0; $i < $n; $i++) {
                if ($mask & (1 << $i)) $subset[] = $cats[$i];
            }
            $subsets[] = $subset;
        }
        return $subsets;
    }

    // Alur 4: telusuri pohon untuk memprediksi satu data
    public function predict(array $record): array
    {
        $node = $this->tree;
        $path = [];
        while (!$node['is_leaf']) {
            $feature = $node['feature'];
            $goLeft = $node['split_type'] === 'numeric'
                ? ((float)$record[$feature] <= $node['split_value'])
                : in_array($record[$feature], $node['split_value'], true);

            $path[] = [
                'feature' => $node['feature_label'],
                'condition' => $this->describeSplit($node, $goLeft),
            ];

            $node = $goLeft ? $node['left'] : $node['right'];
        }
        return [
            'predicted_class' => $node['predicted_class'],
            'confidence' => $node['confidence'],
            'path' => $path,
        ];
    }

    // Ubah kondisi split jadi teks untuk visualisasi pohon
    public function describeSplit(array $node, ?bool $branchIsLeft = null): string
    {
        if ($node['split_type'] === 'numeric') {
            $val = number_format((float)$node['split_value'], 0, ',', '.');
            if ($branchIsLeft === null) return $node['feature_label'] . ' <= ' . $val;
            return $branchIsLeft
                ? $node['feature_label'] . ' <= ' . $val
                : $node['feature_label'] . ' > ' . $val;
        }

        $set = implode(', ', $node['split_value']);
        if ($branchIsLeft === null) return $node['feature_label'] . ' in {' . $set . '}';
        return $branchIsLeft
            ? $node['feature_label'] . ' in {' . $set . '}'
            : $node['feature_label'] . ' not in {' . $set . '}';
    }
}
