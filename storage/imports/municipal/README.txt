Cadastro municipal genérico (CSV ou JSON). Origem: source_type=municipal.

Não há layout oficial ainda. Envie um arquivo com o máximo destes campos
(nomes alternativos entre parênteses):

  external_id          identificador estável da prefeitura (obrigatório na prática)
  nome / name / nome_fantasia
  razao_social / legal_name / razao
  cnpj                 opcional; se vier, precisa ser válido
  codigo_municipio / ibge / municipio_ibge
                       3162955 = São José da Lapa
                       3171204 = Vespasiano
  uf
  logradouro / street / address
  numero / number
  complemento
  bairro / neighborhood
  cep / zip
  telefone / phone
  email
  cnae / cnae_principal
  atividade            texto da atividade (classificação só se for confiável)
  source_url           URL pública da fonte, se houver

JSON aceito: array, { "empresas": [] } ou { "data": [] }.
CSV: primeira linha = cabeçalho.

Empresas de outro IBGE são ignoradas/rejeitadas. O nome da cidade no endereço
não é usado como filtro.

  php tools/import-companies.php --source municipal --city sjl --file municipal/arquivo.json --dry-run
