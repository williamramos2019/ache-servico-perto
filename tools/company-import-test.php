<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
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

expect_true(importer_cnpj_is_valid('11222333000181'), 'CNPJ de exemplo válido');
expect_true(!importer_cnpj_is_valid('11111111111111'), 'CNPJ repetido inválido');
expect_true(!importer_cnpj_is_valid('123'), 'CNPJ curto inválido');

$farm = importer_classify('Farmácia Teste', '4771701', 'Comércio varejista de produtos farmacêuticos');
expect_true($farm['slug'] === 'saude', 'Farmácia/CNAE 4771 mapeia para saude');

$unmapped = importer_classify('XYZ Abcdef', null, null);
expect_true($unmapped['slug'] === null, 'Sem CNAE/keyword fica sem categoria');

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

$sp = $ok;
$sp['ibge'] = '3550308';
$sp['uf'] = 'SP';
expect_true(importer_validate_record($sp, '3162955', 'receita') !== [], 'Fora da cidade é rejeitado');

$invalid = $ok;
$invalid['cnpj'] = '11111111111111';
expect_true(importer_validate_record($invalid, '3162955', 'receita') !== [], 'CNPJ inválido é rejeitado');

$inactive = $ok;
$inactive['active'] = false;
expect_true(importer_validate_record($inactive, '3162955', 'receita') !== [], 'Inativa é rejeitada');

expect_true(importer_name_key('Farmácia Teste') === importer_name_key('Farmacia Teste'), 'Nome normalizado para duplicata');

$file = dirname(__DIR__) . '/tools/fixtures/company-import-sample.json';
$rows = importer_collect_cnpj_file($file, '3162955');
expect_true(count($rows) === 3, 'Arquivo de teste filtra IBGE SJL (3 linhas no município, 1 de SP fora)');

$city = importer_resolve_city_slug('sjl');
expect_true($city === 'sao-jose-da-lapa' && importer_city_ibge($city) === '3162955', 'Alias sjl → IBGE 3162955');

$pathDenied = false;
try {
    importer_safe_file('../load-env.php');
} catch (InvalidArgumentException $e) {
    $pathDenied = true;
}
expect_true($pathDenied, 'Caminho com .. é recusado');

if ($failed > 0) {
    fwrite(STDERR, "$failed teste(s) falharam.\n");
    exit(1);
}
echo "Todos os testes de importação passaram.\n";
exit(0);
