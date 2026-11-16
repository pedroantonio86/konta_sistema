# Roteiro de testes E2E (manual)

Teste ponta a ponta, feito no navegador como um usuário real. Marque `[x]`
em cada item aprovado e anote o que falhar na seção **Registro**, no fim.
Leva cerca de 30 minutos.

## Preparação

- [ ] Reimportar `database/konta.sql` (as datas de exemplo são relativas ao dia).
- [ ] Apagar os arquivos de `storage/uploads/`, **menos** o `.gitkeep`.
- [ ] Ter à mão: um PDF pequeno, uma foto JPG/PNG, um arquivo `.txt` renomeado
      para `.pdf` e um arquivo com mais de 5 MB.
- [ ] Trocar o WhatsApp da Padaria pelo seu número (Empresas → Padaria → Editar).

URL base: `http://localhost/konta_sistema/public/` · Senha de todos: `Konta@2026`

---

## A. Login e sessão

- [ ] **A1** Abrir a URL base sem estar logado → vai para a tela de login.
- [ ] **A2** Clicar em Entrar com tudo vazio → "Campo obrigatório." nos dois campos.
- [ ] **A3** `contador@konta.test` + senha errada → "E-mail ou senha inválidos.", e o e-mail continua preenchido.
- [ ] **A4** E-mail inexistente → a **mesma** mensagem do A3 (não revela quais e-mails existem).
- [ ] **A5** Login correto → painel com "Bem-vindo(a), Ana Contadora!" e o selo "contador".
- [ ] **A6** Clicar em **Sair** → volta ao login com "Você saiu do sistema.". Voltar no navegador e recarregar → login de novo.

## B. Permissões por perfil

Entrar como `padaria@konta.test`:

- [ ] **B1** O menu mostra Painel, Obrigações e Avaliar. **Não** mostra Empresas nem Avaliações.
- [ ] **B2** Digitar `?url=empresas/index` na URL → **403 Acesso negado**.
- [ ] **B3** Digitar `?url=obrigacoes/criar` → 403.
- [ ] **B4** Digitar `?url=avaliacoes/index` → 403.
- [ ] **B5** Digitar `?url=qualquer/coisa` → 404.

## C. Isolamento entre empresas (cliente só vê a própria)

Ainda como `padaria@konta.test`:

- [ ] **C1** Obrigações → "Minhas obrigações" com **5** guias, sem coluna nem filtro de empresa.
- [ ] **C2** Acrescentar `&empresa_id=3` na URL da lista → continuam as mesmas 5.
- [ ] **C3** Abrir uma guia e trocar o `id` da URL por `9` (da Clínica) → **403**.
- [ ] **C4** Painel → os números são só da Padaria (1 vencida, 2 a vencer, 0 pagas, R$ 2.617,55).

## D. Proteção CSRF

- [ ] **D1** Na tela de login, F12 → Elementos → apagar o `<input type="hidden" name="_csrf">` → Entrar → página **"Sessão expirada"**.
- [ ] **D2** Repetir o D1 no botão "Marcar como paga" de uma guia → "Sessão expirada", e a guia continua pendente.
- [ ] **D3** Digitar `?url=empresas/excluir&id=1` na URL (GET) → **405 Método não permitido**.

## E. Empresas (como contador)

- [ ] **E1** Empresas → 4 empresas. Buscar `pao` (sem acento) → acha "Padaria Pão Quente".
- [ ] **E2** Buscar `12.ABC` → acha a Tech Piauí (CNPJ alfanumérico).
- [ ] **E3** + Nova empresa → Cadastrar vazio → 3 campos em vermelho.
- [ ] **E4** CNPJ `11.222.333/0001-82` (DV errado) → "CNPJ inválido.", e os outros campos continuam preenchidos.
- [ ] **E5** CNPJ `11.222.333/0001-81` (já existe) → "CNPJ já cadastrado.".
- [ ] **E6** Cadastrar com CNPJ válido (ex.: `45.723.174/0001-10`) e razão social `<script>alert(1)</script> Teste` → salva e mostra o texto **sem** abrir alerta.
- [ ] **E7** Editar essa empresa, mudar o nome fantasia e salvar → "Empresa atualizada.".
- [ ] **E8** Excluir essa empresa → some da lista, com mensagem.
- [ ] **E9** Excluir a Padaria → bloqueado: "a empresa possui 5 obrigação(ões)".
- [ ] **E10** JR Reparos → Acesso do cliente → criar login com senha de 7 caracteres → erro. Com 8 ou mais → "Acesso criado…". Sair e entrar com ele → vê só as 3 guias da JR.

## F. Obrigações e semáforo (como contador)

- [ ] **F1** Lista com faixas coloridas: vermelhas primeiro, pagas em **cinza** no fim (página 2).
- [ ] **F2** Filtros de situação: Vencida = 3, Vence em breve = 5, Em dia = 5, Paga = 6.
- [ ] **F3** Filtrar Vencida e ir para a página 2 de outro filtro → o filtro continua nos links.
- [ ] **F4** + Nova obrigação → Cadastrar vazio → 5 campos em vermelho.
- [ ] **F5** Padaria + DAS + competência do mês passado → "Já existe DAS MM/AAAA para esta empresa.".
- [ ] **F6** Competência 10/2026 com vencimento 30/09/2026 → "O vencimento não pode ser anterior à competência.".
- [ ] **F7** Cadastrar com valor `1.234,56` → a página da guia mostra "R$ 1.234,56" e o prazo em texto ("vence em X dias").
- [ ] **F8** Editar essa guia → o valor volta como `1.234,56`. Excluir → mensagem de exclusão.

## G. Cobrança pelo WhatsApp (como contador)

- [ ] **G1** Guia pendente da Padaria → **Cobrar no WhatsApp** → abre nova aba do WhatsApp com a mensagem pronta (acentos e quebras de linha corretos).
- [ ] **G2** Guia da Tech Piauí → botão desabilitado. Ao passar o mouse: "Empresa sem WhatsApp válido cadastrado".
- [ ] **G3** Guia paga → sem botão de cobrança.
- [ ] **G4** Como cliente → nenhum botão de cobrança.

## H. Documentos e pagamento (fluxo principal)

Entrar como `padaria@konta.test` e abrir a **DAS vencida**:

- [ ] **H1** Enviar o `.txt` renomeado para `.pdf` → "Envie apenas PDF, JPG ou PNG.".
- [ ] **H2** Enviar o arquivo de mais de 5 MB → "O arquivo passa de 5 MB.".
- [ ] **H3** Enviar sem escolher arquivo → "Selecione um arquivo.".
- [ ] **H4** Enviar o PDF → aparece na lista com tamanho e "Carlos (Padaria)". Clicar → baixa o mesmo arquivo.
- [ ] **H5** Copiar o link de download e trocar o `id` por outro número → 403 ou 404.
- [ ] **H6** Clicar em **✓ Marcar como paga** → selo cinza "Paga", "paga em dd/mm/aaaa", e o botão some.
- [ ] **H7** Painel do cliente → Vencidas = 0, Pagas neste mês = 1.
- [ ] **H8** Como contador, abrir a mesma guia → o comprovante está lá e baixa normalmente.
- [ ] **H9** Abrir `http://localhost/konta_sistema/storage/uploads/` → **403**.
- [ ] **H10** Abrir `http://localhost/konta_sistema/config/config.php` → **403**.
- [ ] **H11** Como contador, excluir essa guia → o arquivo some de `storage/uploads/`.

## I. Painel (como contador, com o banco recém-importado)

- [ ] **I1** Cartões: Vencidas 3 · Vencem em até 7 dias 5 · Pagas neste mês 3 · Total pendente R$ 35.229,60.
- [ ] **I2** Clicar no cartão Vencidas → abre a lista já filtrada.
- [ ] **I3** "Próximas 5 a vencer" mostra a empresa de cada guia, em ordem de vencimento.

## J. Avaliações

- [ ] **J1** Como cliente → Avaliar → enviar sem nota → erro. Com 4 estrelas → "Obrigado pela avaliação!".
- [ ] **J2** Como contador → Avaliações → a média e as barras incluem a nova nota. O comentário aparece com o selo "cliente".

## K. Celular

- [ ] **K1** Com a janela estreita (ou F12 → modo celular), o menu vira um botão que abre e fecha.
- [ ] **K2** As listas de obrigações e empresas rolam para o lado sem quebrar a página.

---

## Registro

| Data | Quem testou | Itens que falharam | Observação |
|---|---|---|---|
|  |  |  |  |
