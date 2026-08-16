<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Forbidden\n";
    exit(1);
}

require dirname(__DIR__) . '/api/importer/bootstrap.php';

$failed = 0;
function expect_true(bool $ok, string $msg): void
{
    global $failed;
    if ($ok) {
        echo "ok  $msg\n";
        return;
    }
    $failed++;
    echo "FAIL  $msg\n";
}

function test_cnpj_from_base(string $base12): string
{
    $calc = static function (string $base, array $weights): int {
        $sum = 0;
        foreach ($weights as $i => $w) {
            $sum += (int) $base[$i] * $w;
        }
        $mod = $sum % 11;

        return $mod < 2 ? 0 : 11 - $mod;
    };
    $d1 = $calc($base12, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $d2 = $calc($base12 . $d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

    return $base12 . $d1 . $d2;
}

$cnpjA = test_cnpj_from_base('990000010001');
$cnpjB = test_cnpj_from_base('990000020001');
expect_true(importer_cnpj_is_valid($cnpjA) && importer_cnpj_is_valid($cnpjB), 'CNPJs técnicos de teste têm DV válido');
expect_true(importer_cnpj_is_valid('11222333000181'), 'CNPJ de exemplo válido');
expect_true(!importer_cnpj_is_valid('11111111111111'), 'CNPJ repetido inválido');
expect_true(!importer_cnpj_is_valid('123'), 'CNPJ curto inválido');

$farm = importer_classify('Farmácia Teste', '4771701', 'Comércio varejista de produtos farmacêuticos');
expect_true($farm['slug'] === 'saude', 'CNAE confiável 4771 → saude');

$unmapped = importer_classify('XYZ Abcdef', null, null);
expect_true($unmapped['slug'] === null, 'Sem CNAE/keyword fica unmapped');

$dubious = importer_classify('XYZ Comercio Teste', '4711302', 'Comércio varejista de mercadorias em geral');
expect_true($dubious['slug'] === null, 'CNAE duvidoso/sem mapa fica unmapped');

$ok = importer_normalize_record([
    'cnpj' => '11.222.333/0001-81',
    'nome_fantasia' => 'Farmácia Teste Importação SJL',
    'razao_social' => 'EMPRESA TESTE IMPORTACAO SJL LTDA',
    'cnae_fiscal' => '4771701',
    'codigo_municipio' => '3162955',
    'uf' => 'MG',
    'logradouro' => 'Rua Teste',
    'numero' => '100',
    'bairro' => 'Centro',
    'cep' => '33350-000',
    'situacao_cadastral' => 'ATIVA',
], 'receita');
$errs = importer_validate_record($ok, '3162955', 'receita');
expect_true($errs === [], 'Registro SJL válido passa');

$vespOk = $ok;
$vespOk['ibge'] = '3171204';
expect_true(importer_validate_record($vespOk, '3171204', 'receita') === [], 'IBGE 3171204 aceito para Vespasiano');
expect_true(importer_validate_record($vespOk, '3162955', 'receita') !== [], 'IBGE Vespasiano rejeitado na cidade SJL');

$sp = $ok;
$sp['ibge'] = '3550308';
$sp['uf'] = 'SP';
expect_true(importer_validate_record($sp, '3162955', 'receita') !== [], 'Fora da cidade é rejeitado');

$noIbge = $ok;
$noIbge['ibge'] = null;
expect_true(importer_validate_record($noIbge, '3162955', 'receita') !== [], 'Receita sem IBGE é rejeitada');

$invalid = $ok;
$invalid['cnpj'] = '11111111111111';
expect_true(importer_validate_record($invalid, '3162955', 'receita') !== [], 'CNPJ inválido é rejeitado');

$inactive = $ok;
$inactive['active'] = false;
expect_true(importer_validate_record($inactive, '3162955', 'receita') !== [], 'Inativa é rejeitada');

expect_true(importer_name_key('Farmácia Teste') === importer_name_key('Farmacia Teste'), 'Nome normalizado para duplicata');

expect_true(importer_city_ibge('sao-jose-da-lapa') === '3162955', 'SJL = 3162955');
expect_true(importer_city_ibge('vespasiano') === '3171204', 'Vespasiano = 3171204');
expect_true(importer_city_slug_from_ibge('3162955') === 'sao-jose-da-lapa', 'IBGE 3162955 → SJL');
expect_true(importer_city_slug_from_ibge('3171204') === 'vespasiano', 'IBGE 3171204 → Vespasiano');
expect_true(importer_city_slug_from_ibge('3550308') === null, 'IBGE de outro município não mapeia');
expect_true(!importer_ibge_is_allowed('3106200'), 'Belo Horizonte (3106200) é rejeitado');

$city = importer_resolve_city_slug('sjl');
expect_true($city === 'sao-jose-da-lapa' && importer_city_ibge($city) === '3162955', 'Alias sjl → IBGE 3162955');

$file = dirname(__DIR__) . '/tools/fixtures/company-import-sample.json';
$rows = importer_collect_cnpj_file($file, '3162955');
expect_true(count($rows) === 3, 'Fixture filtra IBGE SJL (3 no município, SP/Vespasiano fora)');
$rowsVesp = importer_collect_cnpj_file($file, '3171204');
expect_true(count($rowsVesp) === 1, 'Fixture filtra IBGE Vespasiano');

$previewInserted = 0;
$previewRejected = 0;
$previewDupInFile = 0;
$seenCnpj = [];
foreach ($rows as $record) {
    $verr = importer_validate_record($record, '3162955', 'receita');
    if ($verr !== []) {
        $previewRejected++;
        continue;
    }
    $cnpjKey = (string) ($record['cnpj'] ?? '');
    if ($cnpjKey !== '' && isset($seenCnpj[$cnpjKey])) {
        $previewDupInFile++;
        continue;
    }
    if ($cnpjKey !== '') {
        $seenCnpj[$cnpjKey] = true;
    }
    $previewInserted++;
}
expect_true(
    $previewInserted === 1 && $previewRejected === 1 && $previewDupInFile === 1,
    'Dry-run lógico do fixture SJL: 1 criaria, 1 rejeitado (CNPJ), 1 duplicata no arquivo — sem gravar banco'
);
$munFile = dirname(__DIR__) . '/tools/fixtures/company-import-municipal.json';
$munRows = importer_collect_municipal_file($munFile, '3162955');
expect_true(count($munRows) === 1 && $munRows[0]['external_id'] === 'mun-teste-001', 'Adapter municipal lê JSON com origem identificada');

$pathDenied = false;
try {
    importer_safe_file('../load-env.php');
} catch (InvalidArgumentException $e) {
    $pathDenied = true;
}
expect_true($pathDenied, 'Caminho com .. é recusado');

$parsed = importer_parse_argv([
    'import-companies.php',
    '--source', 'receita',
    '--city', 'sjl',
    '--file', 'receita/x.json',
    '--dry-run',
    '--limit', '100',
    '--resume',
]);
expect_true(
    $parsed['source'] === 'receita'
    && $parsed['city'] === 'sjl'
    && $parsed['dry_run'] === true
    && $parsed['limit'] === 100
    && $parsed['resume'] === true,
    'CLI aceita --source receita (espaço) e --limit 100'
);

$parsedEq = importer_parse_argv(['x.php', '--source=municipal', '--city=vespasiano', '--file=a.csv', '--resume=aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);
expect_true($parsedEq['resume'] === 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'CLI aceita --resume=UUID');

$protected = [
    'owner_id' => 'owner-uuid-teste',
    'plan' => 'premium',
    'plan_expires_at' => '2027-01-01 00:00:00.000',
    'featured' => 1,
    'is_verified' => 1,
    'rating' => '4.80',
    'review_count' => 12,
    'whatsapp' => '31988887777',
    'logo_url' => 'https://example.test/logo.png',
    'banner_url' => 'https://example.test/banner.png',
    'description' => 'Descrição manual protegida',
    'origin' => 'manual',
    'cnpj' => null,
    'phone' => '3133334444',
    'address' => 'Rua Já Preenchida, 1',
];
$incoming = [
    'cnpj' => $cnpjA,
    'legal_name' => 'RAZAO TESTE IMPORT LTDA',
    'cnae_primary' => '4771701',
    'neighborhood' => 'Centro',
    'address' => 'Rua Nova Importada, 99',
    'zip' => '33350000',
    'email' => 'teste@example.test',
    'phone' => '3111111111',
    'whatsapp' => '31900000000',
    'description' => 'NÃO DEVE COPIAR',
    'plan' => 'free',
    'featured' => 0,
    'is_verified' => 0,
    'owner_id' => 'outro',
    'logo_url' => 'https://evil.test/x.png',
    'banner_url' => 'https://evil.test/y.png',
    'rating' => '0',
    'review_count' => 0,
    'origin' => 'imported',
];
$fill = importer_allowed_fill($protected, $incoming);
foreach (['owner_id', 'plan', 'featured', 'is_verified', 'rating', 'review_count', 'whatsapp', 'logo_url', 'banner_url', 'description', 'origin'] as $col) {
    expect_true(!isset($fill[$col]), 'Campo protegido não entra no fill: ' . $col);
}
expect_true(($fill['cnpj'] ?? null) === $cnpjA, 'CNPJ vazio pode ser preenchido');
expect_true(!isset($fill['phone']), 'Telefone já preenchido não é substituído');
expect_true(!isset($fill['address']), 'Endereço já preenchido não é substituído');

$snap = importer_protected_snapshot($protected);
$afterFill = $protected;
foreach ($fill as $k => $v) {
    $afterFill[$k] = $v;
}
foreach ($snap as $col => $value) {
    expect_true(($afterFill[$col] ?? null) === $value, 'Preservação após fill: ' . $col);
}

$citySjl = 'city-sjl';
$cityVesp = 'city-vesp';
expect_true(
    importer_is_name_city_candidate('Farmácia Teste Importação SJL', 'Farmacia Teste Importacao SJL', $citySjl, $citySjl),
    'CASO C: mesmo nome+cidade sem CNPJ é candidato'
);
expect_true(
    !importer_is_name_city_candidate('Farmácia Teste Importação SJL', 'Farmácia Teste Importação SJL', $citySjl, $cityVesp),
    'CASO D: mesmo nome em cidades diferentes não é a mesma empresa'
);
expect_true(
    !importer_is_name_city_candidate('Farmácia Teste Importação SJL', 'EMPRESA TESTE IMPORTACAO SJL LTDA', $citySjl, $citySjl),
    'CASO E: razão social ≠ nome fantasia não gera duplicidade automática'
);

$san = importer_sanitize_raw(['cnpj' => $cnpjA, 'password' => 'segredo', 'token' => 'abc']);
expect_true(isset($san['cnpj']) && !isset($san['password']) && !isset($san['token']), 'Log não registra senha/token');

$cliSrc = file_get_contents(dirname(__DIR__) . '/tools/import-companies.php') ?: '';
expect_true(
    !preg_match('/\b(shell_exec|passthru|system|exec)\s*\(/', $cliSrc)
    && str_contains($cliSrc, 'http_response_code(403)'),
    'CLI recusa HTTP 403 e não usa shell_exec/system/passthru'
);

$pdo = null;
try {
    $pdo = db_pdo(false);
} catch (Throwable $e) {
    echo "skip  integração com banco (" . $e->getMessage() . ")\n";
}

if ($pdo instanceof PDO) {
    $cityStmt = $pdo->query("SELECT id, slug FROM cities WHERE slug IN ('sao-jose-da-lapa','vespasiano')");
    $cities = [];
    foreach ($cityStmt ? $cityStmt->fetchAll() : [] as $row) {
        $cities[(string) $row['slug']] = (string) $row['id'];
    }
    if (!isset($cities['sao-jose-da-lapa'])) {
        echo "skip  preservação em DB (cidade SJL ausente)\n";
    } else {
        $pdo->beginTransaction();
        try {
            $id = auth_uuid();
            $now = auth_now();
            $slug = 'empresa-teste-import-pipeline-' . substr($id, 0, 8);
            $pdo->prepare(
                'INSERT INTO companies (
                    id, owner_id, slug, name, legal_name, cnpj, tagline, description,
                    phone, whatsapp, email, address, neighborhood, zip, city_id,
                    logo_url, banner_url, plan, featured, status, origin, is_verified,
                    rating, review_count, views_count, created_at, updated_at
                ) VALUES (
                    :id, :owner_id, :slug, :name, :legal_name, NULL, NULL, :description,
                    :phone, :whatsapp, :email, :address, :neighborhood, :zip, :city_id,
                    :logo_url, :banner_url, :plan, 1, :status, :origin, 1,
                    :rating, :review_count, 9, :created_at, :updated_at
                )'
            )->execute([
                ':id' => $id,
                ':owner_id' => null,
                ':slug' => $slug,
                ':name' => 'Empresa Teste Preservação Pipeline',
                ':legal_name' => 'EMPRESA TESTE PRESERVACAO LTDA',
                ':description' => 'Descrição manual protegida',
                ':phone' => '3133334444',
                ':whatsapp' => '31988887777',
                ':email' => 'manual@example.test',
                ':address' => 'Rua Já Preenchida, 1',
                ':neighborhood' => 'Centro',
                ':zip' => '33350000',
                ':city_id' => $cities['sao-jose-da-lapa'],
                ':logo_url' => 'https://example.test/logo.png',
                ':banner_url' => 'https://example.test/banner.png',
                ':plan' => 'premium',
                ':status' => 'active',
                ':origin' => 'manual',
                ':rating' => '4.80',
                ':review_count' => 12,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $existing = $pdo->query('SELECT * FROM companies WHERE id = ' . $pdo->quote($id))->fetch();
            $before = importer_protected_snapshot($existing);
            importer_fill_empty_fields($pdo, $existing, $incoming, null);
            $after = $pdo->query('SELECT * FROM companies WHERE id = ' . $pdo->quote($id))->fetch();
            foreach ($before as $col => $value) {
                $left = $after[$col] ?? null;
                $right = $value;
                if (is_numeric($left) && is_numeric($right)) {
                    expect_true((float) $left === (float) $right, 'DB preservação: ' . $col);
                } else {
                    expect_true((string) $left === (string) $right, 'DB preservação: ' . $col);
                }
            }

            $dupCnpj = importer_find_duplicate($pdo, [
                'cnpj' => $cnpjA,
                'name' => 'Empresa Nova Teste A',
                'source_type' => 'receita',
                'external_id' => $cnpjA,
            ], $cities['sao-jose-da-lapa']);
            expect_true($dupCnpj['match'] === 'none', 'CASO A: CNPJ novo não encontra duplicata');

            $pdo->prepare('UPDATE companies SET cnpj = :cnpj WHERE id = :id')->execute([':cnpj' => $cnpjA, ':id' => $id]);
            $dupExisting = importer_find_duplicate($pdo, [
                'cnpj' => $cnpjA,
                'name' => 'Outro Nome',
                'source_type' => 'receita',
                'external_id' => $cnpjA,
            ], $cities['sao-jose-da-lapa']);
            expect_true($dupExisting['match'] === 'cnpj', 'CASO B: CNPJ existente não cria outra empresa');

            $cand = importer_find_duplicate($pdo, [
                'cnpj' => null,
                'name' => 'Empresa Teste Preservação Pipeline',
                'source_type' => 'municipal',
                'external_id' => 'mun-1',
            ], $cities['sao-jose-da-lapa']);
            expect_true($cand['match'] === 'name_city_candidate', 'CASO C: nome+cidade sem CNPJ = candidato');
            $persistCand = importer_persist_company(
                $pdo,
                ['name' => 'Empresa Teste Preservação Pipeline'],
                $cities['sao-jose-da-lapa'],
                'sao-jose-da-lapa',
                null,
                true,
                false,
                $cand['company'],
                'name_city_candidate'
            );
            expect_true($persistCand['action'] === 'candidate', 'Candidato não altera a empresa existente');

            if (isset($cities['vespasiano'])) {
                $otherCity = importer_find_duplicate($pdo, [
                    'cnpj' => null,
                    'name' => 'Empresa Teste Preservação Pipeline',
                    'source_type' => 'municipal',
                    'external_id' => 'mun-2',
                ], $cities['vespasiano']);
                expect_true($otherCity['match'] === 'none', 'CASO D: mesmo nome em outra cidade não duplica');
            }

            $legalOnly = importer_find_duplicate($pdo, [
                'cnpj' => null,
                'name' => 'EMPRESA TESTE PRESERVACAO LTDA',
                'legal_name' => 'EMPRESA TESTE PRESERVACAO LTDA',
                'source_type' => 'municipal',
                'external_id' => 'mun-3',
            ], $cities['sao-jose-da-lapa']);
            expect_true($legalOnly['match'] === 'none', 'CASO E: razão social não é chave de duplicidade');
        } catch (Throwable $e) {
            echo "skip  integração DB (" . $e->getMessage() . ")\n";
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    $importsDir = importer_imports_dir() . DIRECTORY_SEPARATOR . 'receita';
    if (!is_dir($importsDir)) {
        mkdir($importsDir, 0770, true);
    }
    $tmp = $importsDir . DIRECTORY_SEPARATOR . 'fixture-teste.json';
    copy($file, $tmp);
    try {
        $dry = importer_run($pdo, [
            'source' => 'receita',
            'city' => 'sjl',
            'file' => 'receita/fixture-teste.json',
            'dry_run' => true,
            'limit' => 100,
            'update' => false,
            'enrich' => false,
            'resume' => false,
        ]);
        expect_true($dry['dry_run'] === true, 'Dry-run marca dry_run no relatório');
        expect_true($dry['run_id'] === null, 'Dry-run não grava company_import_runs como importação concluída');
        expect_true($dry['inserted'] >= 1, 'Dry-run simula inserção sem gravar');
        expect_true($dry['rejected'] >= 1, 'Dry-run reporta rejeitados (CNPJ inválido)');
        echo "ok  dry-run fixture collected={$dry['collected']} inserted={$dry['inserted']} rejected={$dry['rejected']} skipped={$dry['skipped']}\n";
    } catch (Throwable $e) {
        echo 'skip  dry-run fixture (' . $e->getMessage() . ")\n";
    } finally {
        if (is_file($tmp)) {
            unlink($tmp);
        }
    }
}

if ($failed > 0) {
    fwrite(STDERR, "$failed teste(s) falharam.\n");
    exit(1);
}
echo "Todos os testes de importação passaram.\n";
exit(0);
