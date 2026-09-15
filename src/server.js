import express from 'express';
import { rotas } from './routes.js';
import { carregarLocalizacao } from './data/localizacao.js';
import { anos } from './data/feriados.js';

const PORTA = Number(process.env.PORT) || 3000;

const app = express();
app.use(rotas);

app.use((req, res) => {
  res.status(404).json({ erro: `Rota não encontrada: ${req.method} ${req.path}` });
});

app.use((erro, req, res, next) => {
  console.error(erro);
  res.status(500).json({ erro: 'Erro interno.' });
});

const local = carregarLocalizacao();
const { min, max } = anos();

app.listen(PORTA, () => {
  console.log(`API de feriados em http://localhost:${PORTA}`);
  console.log(`Localização: ${local.estados} estados, ${local.municipios} municípios`);
  console.log(`Feriados: ${min}–${max}`);
});
