import { Router } from 'express';
import { validarIso, diaDaSemana } from './lib/datas.js';
import { anos, anoSuportado, feriadosDoAno, feriadosNaData, anosEmCache } from './data/feriados.js';
import { ufValida, resolverMunicipio, listarEstados, listarMunicipios } from './data/localizacao.js';

export const rotas = Router();

/** Lê `uf` e `municipio` da query. Retorna { ok, local } ou { ok: false, status, erro }. */
function lerLocal(query) {
  const uf = query.uf ? String(query.uf).trim().toUpperCase() : null;

  if (uf && !ufValida(uf)) {
    return { ok: false, status: 400, erro: `UF inválida: ${uf}` };
  }

  if (!query.municipio) {
    return { ok: true, local: { uf, codigoIbge: null, municipio: null } };
  }

  const resolvido = resolverMunicipio(query.municipio, uf);
  if (!resolvido.ok) return resolvido;

  const { municipio } = resolvido;
  return {
    ok: true,
    local: { uf: municipio.uf, codigoIbge: municipio.codigoIbge, municipio: municipio.nome },
  };
}

function erroDeAno(ano) {
  const { min, max } = anos();
  return { status: 404, erro: `Ano fora do intervalo suportado entre ${min} e ${max}.` };
}

function descreverLocal(local) {
  return { uf: local.uf, municipio: local.municipio, codigoIbge: local.codigoIbge };
}

rotas.get('/health', (req, res) => {
  const { min, max } = anos();
  res.json({ status: 'ok', anoMin: min, anoMax: max, anosEmCache: anosEmCache() });
});

rotas.get('/feriado', (req, res) => {
  const data = validarIso(req.query.data);
  if (!data) {
    return res.status(400).json({ erro: "Parâmetro 'data' inválido. Use YYYY-MM-DD." });
  }
  if (!anoSuportado(data.ano)) {
    const { status, erro } = erroDeAno(data.ano);
    return res.status(status).json({ erro });
  }

  const local = lerLocal(req.query);
  if (!local.ok) return res.status(local.status).json({ erro: local.erro });

  const encontrados = feriadosNaData(data.iso, data.ano, local.local);
  const feriados = encontrados.filter((f) => f.tipo !== 'facultativo');
  const facultativos = encontrados.filter((f) => f.tipo === 'facultativo');

  res.json({
    data: data.iso,
    diaDaSemana: diaDaSemana(data.iso),
    feriado: feriados.length > 0,
    pontoFacultativo: facultativos.length > 0,
    abrangencia: [...new Set(feriados.map((f) => f.tipo))],
    feriados,
    pontosFacultativos: facultativos,
    local: descreverLocal(local.local),
  });
});

rotas.get('/feriados/:ano', (req, res) => {
  const ano = Number(req.params.ano);
  if (!/^\d{4}$/.test(req.params.ano) || !anoSuportado(ano)) {
    const { status, erro } = erroDeAno(ano);
    return res.status(status).json({ erro });
  }

  const local = lerLocal(req.query);
  if (!local.ok) return res.status(local.status).json({ erro: local.erro });

  const lista = feriadosDoAno(ano, local.local).map((f) => ({
    ...f,
    diaDaSemana: diaDaSemana(f.data),
  }));

  res.json({ ano, local: descreverLocal(local.local), total: lista.length, feriados: lista });
});

rotas.get('/estados', (req, res) => {
  res.json(listarEstados());
});

rotas.get('/municipios', (req, res) => {
  const uf = req.query.uf ? String(req.query.uf).trim().toUpperCase() : null;
  if (!uf) return res.status(400).json({ erro: "Parâmetro 'uf' é obrigatório." });
  if (!ufValida(uf)) return res.status(400).json({ erro: `UF inválida: ${uf}` });

  const lista = listarMunicipios(uf);
  res.json({ uf, total: lista.length, municipios: lista });
});
