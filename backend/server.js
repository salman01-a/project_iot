import express from 'express';
import { SaveDataHandler, getDataHandler } from './handler.js';

const app = express();
const PORT = 8000;

app.use(express.json());

app.get('/data', getDataHandler);
app.post('/data', SaveDataHandler);

app.listen(PORT, () => {
  console.log(`Server berjalan di http://localhost:${PORT}`);
});
