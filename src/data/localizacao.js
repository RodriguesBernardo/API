import fs from 'node:fs';
import path from 'node:path';
import { DIR_LOCALIZACAO } from './paths.js';
import { normalizar } from '../lib/normalize.js';

const ufPorCodigo = new Map();      // codigo_uf -> "SP"
const estadoPorUf = new Map();      // "SP" -> { uf, nome, regiao }
const municipioPorIbge = new Map(); // codigo_ibge -> { codigoIbge, nome, uf }
const ibgePorNomeUf = new Map();    // "sao paulo|SP" -> [codigo_ibge, ...]
const municipiosPorUf = new Map();  // "SP" -> [{ codigoIbge, nome }, ...]

function lerJson(...partes) {
  return JSON.parse(fs.readFileSync(path.join(...partes), 'utf8'));
}

/** Carrega estados e municípios em memória. Chamado uma vez, no boot. */
export function carregarLocalizacao() {
  const estados = lerJson(DIR_LOCALIZACAO, 'estados', 'estados.json');
  for (const e of estados) {
    ufPorCodigo.set(e.codigo_uf, e.uf);
    estadoPorUf.set(e.uf, { uf: e.uf, nome: e.nome, regiao: e.regiao });
  }

  const municipios = lerJson(DIR_LOCALIZACAO, 'municipios', 'municipios.json');
  for (const m of municipios) {
    const uf = ufPorCodigo.get(m.codigo_uf);
    if (!uf) continue;

    const registro = { codigoIbge: m.codigo_ibge, nome: m.nome, uf };
    municipioPorIbge.set(m.codigo_ibge, registro);

    const chave = `${normalizar(m.nome)}|${uf}`;
    if (!ibgePorNomeUf.has(chave)) ibgePorNomeUf.set(chave, []);
    ibgePorNomeUf.get(chave).push(m.codigo_ibge);

    if (!municipiosPorUf.has(uf)) municipiosPorUf.set(uf, []);
    municipiosPorUf.get(uf).push({ codigoIbge: m.codigo_ibge, nome: m.nome });
  }

  for (const lista of municipiosPorUf.values()) {
    lista.sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR'));
  }

  return { estados: estadoPorUf.size, municipios: municipioPorIbge.size };
}

export function ufValida(uf) {
  return estadoPorUf.has(String(uf).toUpperCase());
}

export function listarEstados() {
  return [...estadoPorUf.values()];
}

export function listarMunicipios(uf) {
  return municipiosPorUf.get(String(uf).toUpperCase()) ?? [];
}

/**
 * Resolve o parâmetro `municipio` (código IBGE ou nome) para um registro.
 * Retorna { ok: true, municipio } ou { ok: false, erro, status }.
 */
export function resolverMunicipio(entrada, uf) {
  const texto = String(entrada).trim();

  if (/^\d+$/.test(texto)) {
    const municipio = municipioPorIbge.get(Number(texto));
    if (!municipio) {
      return { ok: false, status: 400, erro: `Código IBGE não encontrado: ${texto}` };
    }
    if (uf && municipio.uf !== uf) {
      return {
        ok: false,
        status: 400,
        erro: `Município ${municipio.nome} pertence a ${municipio.uf}, não a ${uf}.`,
      };
    }
    return { ok: true, municipio };
  }

  if (!uf) {
    return { ok: false, status: 400, erro: "Informe 'uf' ao buscar município por nome." };
  }

  const codigos = ibgePorNomeUf.get(`${normalizar(texto)}|${uf}`) ?? [];
  if (codigos.length === 0) {
    return { ok: false, status: 400, erro: 'Município não encontrado para a UF informada.' };
  }
  if (codigos.length > 1) {
    return {
      ok: false,
      status: 400,
      erro: `Mais de um município chamado "${texto}" em ${uf}. Use o código IBGE: ${codigos.join(', ')}.`,
    };
  }
  return { ok: true, municipio: municipioPorIbge.get(codigos[0]) };
}
