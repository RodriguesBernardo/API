# Link do GitHub que que peguei o feriados-brasil
https://github.com/joaopbini/feriados-brasil/tree/master

## Para baixar o repositório feriados-brasil
git submodule update --init --recursive

# API de Feriados BR

API que responde se uma data é feriado no Brasil — nacional, estadual, municipal e ponto facultativo. Dados locais (`feriados-brasil/dados`), sem chamada externa. Cobertura: **2010–2026**.

PHP puro, sem framework e sem Composer.

## Requisitos

- PHP 7.4+ (compatível com 8.x)
- Extensões `intl` e `mbstring` habilitadas
- Apache com `mod_rewrite` (ou Nginx — ver seção abaixo)

## Estrutura

```
public/
  index.php    # front controller — todas as rotas passam por aqui
  .htaccess    # reescreve tudo para index.php (Apache)
src/
  routes.php
  lib/          # Datas.php, Normalize.php
  data/         # Paths.php, Feriados.php, Localizacao.php
```

O servidor web (Apache/Nginx) deve apontar para a pasta `public/`.

## Instalar e rodar

### Opção 1: servidor embutido do PHP (rápido, sem configurar nada)

```bash
cd API
php -S localhost:85/API -t public public/index.php
```

Testar rápido se subiu:

```bash
curl http://localhost:85/API/health
```

## Autenticação

Todas as rotas exigem o parâmetro `?token=SEU_TOKEN` na URL. O token padrão fica em `src/config.php` (constante `API_TOKEN`) — troque o valor lá, ou defina a variável de ambiente `API_TOKEN` para sobrescrever sem editar o código. Requisição sem token ou com token errado retorna `401`.

```bash
curl "http://localhost:85/API/health?token=MU7tmUw0JivhBf6eA3IL5QtO3"
```

## Endpoints

Os exemplos abaixo usam `http://localhost:85/API` (servidor embutido do PHP) e omitem o `token` por brevidade — lembre de incluir `&token=SEU_TOKEN` (ou `?token=...` se não houver outros parâmetros) em cada chamada real. Se estiver rodando via Apache/XAMPP, troque pela sua URL.

### `GET /feriado`

Responde se uma data específica é feriado.

| Query param | Obrigatório | Descrição |
|---|---|---|
| `data` | sim | Data em `YYYY-MM-DD` |
| `uf` | não | Sigla do estado (ex: `SP`). Sem ela, só considera nacional |
| `municipio` | não | Código IBGE ou nome do município. Nome exige `uf` junto |

Exemplo (Porto Alegre/RS):

```bash
curl "http://localhost:85/API/feriado?data=2026-02-02&uf=RS&municipio=4314902"
```

```json
{
  "data": "2026-02-02",
  "diaDaSemana": "segunda-feira",
  "feriado": true,
  "pontoFacultativo": false,
  "abrangencia": ["municipal"],
  "feriados": [
    { "data": "2026-02-02", "nome": "Nossa Senhora dos Navegantes", "tipo": "municipal", "uf": "RS", "codigoIbge": 4314902 }
  ],
  "pontosFacultativos": [],
  "local": { "uf": "RS", "municipio": "Porto Alegre", "codigoIbge": 4314902 }
}
```

Também aceita nome em vez do código IBGE (exige `uf` junto pra desambiguar):

```bash
curl "http://localhost:85/API/feriado?data=2026-02-02&uf=RS&municipio=Porto Alegre"
```

Feriado estadual do RS (Revolução Farroupilha, 20/09):

```bash
curl "http://localhost:85/API/feriado?data=2026-09-20&uf=RS"
```

### `GET /feriados/:ano`

Lista todos os feriados do ano para o local informado (mesmos params `uf`/`municipio` da rota acima).

```bash
curl "http://localhost:85/API/feriados/2026?uf=RS"
curl "http://localhost:85/API/feriados/2026?uf=RS&municipio=4314902"
```

### `GET /estados`

Lista os 27 estados (sigla, nome, região).

### `GET /municipios?uf=RS`

Lista municípios da UF com nome e código IBGE.

### `GET /health`

Status da API e intervalo de anos suportado.

## Erros

Toda resposta de erro segue `{ "erro": "mensagem" }`.

| Situação | Status |
|---|---|
| `data` ausente/inválida | 400 |
| Ano fora de 2010–2026 | 404 |
| UF inválida | 400 |
| Município não encontrado | 400 |
| `municipio` por nome sem `uf` | 400 |

## Sobre os dados

- `feriado: true` considera nacional, estadual e municipal.
- Ponto facultativo (Carnaval, Corpus Christi, véspera de feriado) **não conta como feriado** — vem separado em `pontoFacultativo` / `pontosFacultativos`.
- Páscoa não consta na base local (cai sempre num domingo, sem efeito em dia útil) — por isso diverge da BrasilAPI, que a lista como nacional.
- Sem processo persistente (diferente de um servidor Node), cada requisição PHP lê os JSONs do disco de novo — não muda nenhuma resposta, só o perfil de performance (leitura local é barata).

## Testando no Postman

### Opção 1: importar a coleção pronta

1. Abra o Postman.
2. **File → Import** (ou botão **Import** no canto superior esquerdo).
3. Selecione o arquivo `postman_collection.json` (raiz deste projeto).
4. A coleção **"API Feriados BR"** aparece na barra lateral, já com todos os endpoints e alguns casos de erro prontos para rodar.
5. Confira as variáveis de coleção `baseUrl` e `token` (clique nos "..." da coleção → **Edit** → aba **Variables**) — ajuste `baseUrl` conforme como estiver rodando, e `token` deve bater com `API_TOKEN` (`src/config.php`). O token já é enviado automaticamente como query param em toda requisição (configurado na aba **Authorization** da coleção).
6. Com o servidor rodando, clique em qualquer request → **Send**.

### Opção 2: montar manualmente

1. **New → HTTP Request**.
2. Método `GET`.
3. URL: `http://localhost:85/API/feriado`.
4. Aba **Params**, adicione:
   - `data` = `2026-02-02`
   - `uf` = `RS` (opcional)
   - `municipio` = `4314902` (opcional — código IBGE de Porto Alegre)
5. **Send**. Resposta esperada: status `200`, corpo JSON com `feriado: true/false`.

### Casos pra validar no Postman — foco RS

Testado e conferido contra o servidor local (todos batendo com o esperado):

| O que testar | Request | Esperado |
|---|---|---|
| Feriado nacional | `GET /feriado?data=2026-01-01` | `200`, `feriado: true`, `abrangencia: ["nacional"]` |
| Dia comum | `GET /feriado?data=2026-01-05` | `200`, `feriado: false` |
| Ponto facultativo não é feriado | `GET /feriado?data=2026-02-16` | `200`, `feriado: false`, `pontoFacultativo: true` |
| Feriado estadual RS (Revolução Farroupilha) | `GET /feriado?data=2026-09-20&uf=RS` | `200`, `feriado: true`, `abrangencia: ["estadual"]` |
| Mesmo feriado estadual, UF errada | `GET /feriado?data=2026-09-20&uf=SC` | `200`, `feriado: false` (feriado do RS não vaza pra SC) |
| Feriado municipal por código IBGE (Porto Alegre) | `GET /feriado?data=2026-02-02&uf=RS&municipio=4314902` | `200`, `feriado: true`, `abrangencia: ["municipal"]` |
| Feriado municipal por nome (Porto Alegre) | `GET /feriado?data=2026-02-02&uf=RS&municipio=Porto Alegre` | `200`, mesmo resultado do código IBGE |
| Feriado municipal, município errado | `GET /feriado?data=2026-02-02&uf=RS&municipio=Caxias do Sul` | `200`, `feriado: false` (feriado de POA não vaza pra Caxias) |
| Lista de feriados do ano (RS todo) | `GET /feriados/2026?uf=RS` | `200`, `total: 22` (nacional + estadual, sem filtrar município) |
| Lista de feriados do ano (Porto Alegre) | `GET /feriados/2026?uf=RS&municipio=4314902` | `200`, `total: 25` (inclui os municipais de POA) |
| Lista de municípios do RS | `GET /municipios?uf=RS` | `200`, `total: 497` |
| Data inválida | `GET /feriado?data=2026-13-40` | `400`, corpo `{"erro": "..."}` |
| Ano fora do intervalo | `GET /feriado?data=2030-01-01` | `404` |
| UF inválida | `GET /feriado?data=2026-01-01&uf=XX` | `400` |
| Nome de município sem `uf` | `GET /feriado?data=2026-01-01&municipio=Caxias do Sul` | `400`, `"erro":"Informe 'uf' ao buscar município por nome."` |
| Código IBGE de UF errada | `GET /feriado?data=2026-01-01&uf=SC&municipio=4314902` | `400`, `"erro":"Município Porto Alegre pertence a RS, não a SC."` |

Dica: no Postman, aba **Tests** de cada request dá pra automatizar essas conferências, ex:

```javascript
pm.test("status 200", () => pm.response.to.have.status(200));
pm.test("é feriado", () => pm.response.json().feriado === true);
```

Rodando a coleção inteira de uma vez: clique nos "..." da coleção → **Run collection** → **Run API Feriados BR**.
