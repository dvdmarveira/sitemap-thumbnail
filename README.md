# 📰 Thumbnail Generator para Posts do Google News

Este projeto é composto por dois scripts PHP responsáveis por gerar e registrar URLs de thumbnails (miniaturas) com base em posts listados no sitemap do Google News.

## 📁 Scripts

### `process.php`

#### ✅ Objetivo
Automatiza a extração de postagens recentes do Google News Sitemap, recupera os IDs dos posts diretamente das páginas HTML e monta URLs válidas para thumbnails.

#### ⚙️ Configurações
- `sitemap_url`: URL do sitemap do Google News.
- `thumbnail_base_url`: Base da URL do script `thumbnail.php`.
- `log_file`: Caminho para salvar o log do processo.
- `output_file`: Caminho para salvar as URLs geradas.

#### 🔍 Funcionalidades
- Log de atividades em `thumbnail_processing.log`.
- Leitura de XML do sitemap e extração dos links dos posts.
- Extração do ID do post via múltiplos padrões de regex.
- Extração de imagens destacadas via meta tags e classes comuns.
- Geração de URLs de thumbnails no formato:

```
$thumbnail_base_url . '?id=' . $post_id
```

- URLs válidas salvas em `thumbnail_urls.txt`.

---

### `thumbnail.php`

#### ✅ Objetivo
Gera dinamicamente imagens de thumbnail com base em parâmetros de URL.

#### 🔧 Parâmetros esperados via URL
- `id`: ID do post (obrigatório)
- `title`, `subtitle`, `category`: Textos opcionais
- `image`: URL de imagem de fundo
- `size`: Tamanho da imagem (`600x315`, etc)

#### ⚙️ Funcionalidades
- Validação e parsing dos parâmetros
- Geração da imagem usando biblioteca GD
- Adição de textos personalizados
- Saída com `image/png` renderizado

---

## 🧪 Exemplo de Fluxo

1. Executar `process.php` para gerar URLs de thumbnail.
2. Cada URL gerada aponta para `thumbnail.php?id=...`.
3. `thumbnail.php` gera a imagem dinamicamente.

---

## 🚧 Requisitos

- PHP 7.0+
- Extensão GD habilitada
- Permissão de escrita para logs

---

## 📌 Considerações

- Independente do WordPress (não requer banco ou API WP)
- Extensível para novos padrões
- Útil para automação editorial e social media

