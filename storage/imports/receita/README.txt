Arquivos locais da Receita Federal / dados abertos CNPJ.

O importador NÃO baixa a base. Coloque aqui um JSON ou CSV já filtrado.

Campos reconhecidos (cabeçalho CSV em minúsculas ou chaves JSON):

  cnpj
  cnpj_basico, cnpj_ordem, cnpj_dv
  razao_social
  nome_fantasia
  cnae_fiscal
  cnae_fiscal_descricao
  codigo_municipio   (somente 3162955 = SJL ou 3171204 = Vespasiano)
  uf                 (MG)
  logradouro, numero, complemento, bairro, cep
  situacao_cadastral (ATIVA ou 02)
  ddd_telefone_1 / telefone
  email

JSON aceito:
  [ { ... }, { ... } ]
  { "empresas": [ ... ] }
  { "data": [ ... ] }

Exemplo de comando (depois do arquivo real existir):

  php tools/import-companies.php --source receita --city sjl --file receita/SEU-ARQUIVO.json --dry-run
  php tools/import-companies.php --source receita --city vespasiano --file receita/SEU-ARQUIVO.json --dry-run --limit=100

Não commitar a base completa neste repositório.
