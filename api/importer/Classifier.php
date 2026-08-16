<?php

declare(strict_types=1);

/**
 * Maps CNAE / keywords onto the 27 existing category slugs. Never creates categories.
 *
 * @return array{slug: ?string, reason: string}
 */
function importer_classify(string $name, ?string $cnae, ?string $cnaeDescription): array
{
    $hay = importer_lower(trim($name . ' ' . (string) $cnaeDescription));
    $code = $cnae !== null ? preg_replace('/\D+/', '', $cnae) : '';

    $prefixes = [
        '4771' => 'saude',
        '861' => 'saude',
        '862' => 'saude',
        '863' => 'saude',
        '864' => 'saude',
        '865' => 'saude',
        '866' => 'saude',
        '869' => 'saude',
        '87' => 'saude',
        '561' => 'alimentacao',
        '562' => 'alimentacao',
        '10' => 'alimentacao',
        '11' => 'alimentacao',
        '4721' => 'alimentacao',
        '452' => 'automotivo',
        '453' => 'automotivo',
        '454' => 'automotivo',
        '451' => 'automotivo',
        '9602' => 'beleza-estetica',
        '412' => 'construcao-civil',
        '411' => 'construcao-civil',
        '42' => 'construcao-civil',
        '439' => 'construcao-civil',
        '433' => 'pintura',
        '4321' => 'eletricistas',
        '4322' => 'encanadores',
        '85' => 'educacao',
        '813' => 'jardinagem',
        '011' => 'jardinagem',
        '013' => 'jardinagem',
        '691' => 'advocacia',
        '692' => 'contabilidade',
        '62' => 'tecnologia',
        '63' => 'tecnologia',
        '49' => 'transportes',
        '50' => 'transportes',
        '52' => 'transportes',
        '141' => 'moda',
        '4781' => 'moda',
        '750' => 'pets',
        '9609' => 'pets',
        '68' => 'imobiliaria',
        '823' => 'eventos',
        '90' => 'eventos',
        '931' => 'fitness',
        '80' => 'seguranca',
        '181' => 'servicos-graficos',
        '182' => 'servicos-graficos',
        '95' => 'assistencia-tecnica',
        '162' => 'marcenaria',
        '310' => 'marcenaria',
        '742' => 'fotografia',
        '79' => 'turismo',
        '812' => 'higienizacao',
        '81' => 'higienizacao',
    ];

    if (is_string($code) && $code !== '') {
        uksort($prefixes, static fn ($a, $b): int => strlen((string) $b) <=> strlen((string) $a));
        foreach ($prefixes as $prefix => $slug) {
            $prefix = (string) $prefix;
            if (str_starts_with($code, $prefix)) {
                return ['slug' => $slug, 'reason' => 'cnae:' . $prefix];
            }
        }
    }

    $keywords = [
        'saude' => ['farmacia', 'farmácia', 'clinica', 'clínica', 'dentista', 'medico', 'médico', 'hospital', 'laboratorio', 'laboratório'],
        'alimentacao' => ['restaurante', 'lanchonete', 'padaria', 'pizzaria', 'bar ', 'delivery', 'marmita'],
        'automotivo' => ['oficina', 'auto peca', 'auto peça', 'mecanica', 'mecânica', 'borracharia', 'funilaria'],
        'beleza-estetica' => ['salao', 'salão', 'cabeleireiro', 'barbearia', 'estetica', 'estética', 'manicure'],
        'construcao-civil' => ['construcao', 'construção', 'pedreiro', 'empreiteira', 'reforma'],
        'pintura' => ['pintor', 'pintura'],
        'eletricistas' => ['eletricista', 'eletrica', 'elétrica'],
        'encanadores' => ['encanador', 'hidraulica', 'hidráulica', 'desentupidora'],
        'educacao' => ['escola', 'curso', 'colegio', 'colégio', 'faculdade'],
        'jardinagem' => ['jardim', 'jardinagem', 'paisagismo'],
        'advocacia' => ['advocacia', 'advogado'],
        'contabilidade' => ['contabil', 'contábil', 'contador'],
        'tecnologia' => ['informatica', 'informática', 'software', 'ti ', 'desenvolvimento'],
        'transportes' => ['frete', 'mudanca', 'mudança', 'transportadora'],
        'moda' => ['boutique', 'confeccao', 'confecção', 'roupa'],
        'pets' => ['pet shop', 'veterinar', 'banho e tosa'],
        'imobiliaria' => ['imobiliaria', 'imobiliária', 'corretor'],
        'eventos' => ['buffet', 'cerimonial', 'festas'],
        'fitness' => ['academia', 'crossfit', 'pilates', 'musculacao', 'musculação'],
        'seguranca' => ['seguranca', 'segurança', 'vigilancia', 'vigilância'],
        'servicos-graficos' => ['grafica', 'gráfica', 'impressao', 'impressão'],
        'assistencia-tecnica' => ['assistencia', 'assistência', 'conserto', 'celular'],
        'marcenaria' => ['marcenaria', 'moveis', 'móveis sob medida'],
        'fotografia' => ['foto', 'estudio', 'estúdio fotograf'],
        'turismo' => ['turismo', 'agencia de viagem', 'agência de viagem'],
        'higienizacao' => ['higienizacao', 'higienização', 'limpeza de sofa', 'limpeza de sofá'],
        'empresas' => ['comercio', 'comércio', 'loja'],
    ];

    foreach ($keywords as $slug => $words) {
        foreach ($words as $word) {
            if ($word !== '' && str_contains($hay, $word)) {
                return ['slug' => $slug, 'reason' => 'keyword:' . $word];
            }
        }
    }

    return ['slug' => null, 'reason' => 'unmapped'];
}
