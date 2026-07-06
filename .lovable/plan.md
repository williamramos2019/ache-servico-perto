## Objetivo

Transformar o marketplace atual em um **App da Cidade** focado em **Vespasiano** e **São José da Lapa**, mantendo o guia de empresas/serviços que já existe e adicionando uma seção de **Serviços Públicos** (prefeitura, saúde, segurança, educação, telefones úteis).

## O que muda

### 1. Limpar cidades
- Remover Belo Horizonte, Lagoa Santa e qualquer outra cidade da tabela `cities`.
- Manter apenas **Vespasiano** e **São José da Lapa**.
- Desativar (não deletar) empresas cadastradas fora dessas duas cidades para não perder histórico. Filtro global do app passa a considerar só essas duas.
- Remover o seletor "Todas as cidades / BH / Lagoa Santa" da SearchBar — vira um toggle simples entre as duas cidades (ou "Ambas").

### 2. Nova seção: Serviços Públicos
Nova estrutura no banco:

- **`public_service_categories`** — categorias fixas: Saúde, Educação, Segurança, Prefeitura, Transporte, Assistência Social, Emergências.
- **`public_services`** — cada item: nome, categoria, cidade (vespasiano/sjl), endereço, telefone(s), horário de funcionamento, descrição, coordenadas (lat/lng), site, e-mail, tipo (hospital, UBS, escola, delegacia, secretaria, etc.), ativo.
- **`emergency_contacts`** — atalho fixo para SAMU 192, Bombeiros 193, Polícia 190, Defesa Civil, Guarda Municipal (por cidade).

Todas com RLS: leitura pública (`TO anon`), escrita só admin (`has_role('admin')`).

### 3. Novas rotas
- `/cidade/vespasiano` e `/cidade/sao-jose-da-lapa` — página-cidade com resumo, atalhos de emergência, categorias de serviço público, e empresas em destaque da cidade.
- `/servicos-publicos` — índice geral, filtro por cidade + categoria.
- `/servicos-publicos/$id` — detalhe do serviço público (mapa, telefone, horário, botão ligar/rota).
- `/emergencia` — tela grande, botões diretos de ligação (SAMU, Bombeiros, Polícia, UBS 24h mais próxima).

### 4. Home reformulada
Nova estrutura da home:
1. Hero com escolha da cidade (Vespasiano / São José da Lapa).
2. **Botão vermelho fixo "Emergência"** no canto (acessível de qualquer tela em mobile).
3. Grid "Serviços Públicos" (6 categorias com ícone).
4. "Explore por categoria" (guia de empresas — como já existe).
5. Empresas em destaque.
6. Rodapé cívico (link prefeitura oficial, telefones úteis).

### 5. Admin
Adicionar no painel admin as novas telas:
- Lista/CRUD de `public_services`.
- Lista/CRUD de `emergency_contacts`.
- Mantém todo o admin de empresas atual.

### 6. Marca
Como você não respondeu, vou manter **"AgendaAqui"** por enquanto, mas adicionar o subtítulo **"Vespasiano & São José da Lapa"** no header e no `<title>`. Se quiser mudar o nome depois, é uma edição rápida.

## O que NÃO vou fazer nesta rodada
- Não vou popular com dados reais (você disse "cadastro manual pelo admin"). Vou deixar o banco vazio + admin pronto pra você preencher. Se quiser, insiro os **contatos de emergência básicos** (SAMU/Bombeiros/Polícia) que são nacionais — me confirme.
- Não vou incluir notícias, eventos, transporte nem turismo (fora do escopo escolhido).
- Não vou remover fisicamente empresas de outras cidades (só desativar), pra não perder dado.

## Detalhes técnicos

- **Banco:** 3 novas tabelas + policies + GRANTs conforme padrão do projeto. Enum `city_slug` ('vespasiano','sao-jose-da-lapa').
- **Queries:** novo `src/lib/publicServices.ts` com `createServerFn` público (leitura anon). Cache TanStack Query com `staleTime: 5min`.
- **UI:** reaproveitar `CategoryCard`, `Carousel`, tokens do design system existentes. Nada de cores hardcoded.
- **Filtro global de cidade:** persistido em `localStorage` (`selected_city`), lido no header e propagado às queries de empresas e serviços públicos.
- **SearchBar:** simplificar — remover Select genérico, virar toggle das 2 cidades.
- **Rotas antigas de cidade** (`/cidade/[outras]`): 404 automático (só existirão as duas).

## Ordem de execução
1. Migration (tabelas + enum + policies + GRANTs + soft-disable de empresas fora do escopo).
2. Server fns + queries de serviços públicos.
3. Rotas novas (`/cidade/...`, `/servicos-publicos`, `/emergencia`).
4. Home reformulada + header com seletor de cidade.
5. Admin CRUD dos novos recursos.
6. Ajustar SearchBar e remover cidades no seletor.

Posso seguir?
