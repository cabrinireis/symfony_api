# Processador de Arquivos ODS

Sistema completo para upload e processamento de arquivos `.ods` com foco na aba "XX) Onglet technique - TOTAL".

## Funcionalidades

- ✅ Upload de arquivos .ods (até 20MB)
- ✅ Leitura automática da aba "XX) Onglet technique - TOTAL"
- ✅ Validação de dados performática
- ✅ Interface web para testes
- ✅ API REST para integração
- ✅ Identificação de erros e avisos
- ✅ Resumo estatístico do processamento

## Estrutura do Projeto

```
symfony_api/
├── docker/
│   └── php/
│       └── Dockerfile          # Configurado com extensões necessárias
├── src/
│   ├── Controller/
│   │   └── FileUploadController.php  # Endpoints da API
│   └── Service/
│       └── OdsProcessorService.php   # Lógica de processamento
├── public/
│   └── upload.html            # Interface de teste
└── config/
    └── services.yaml          # Configuração de serviços
```

## Configuração do Ambiente

### 1. Reconstruir Container Docker

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### 2. Instalar Dependências

```bash
docker exec -it symfony_api_app composer install
```

### 3. Verificar Permissões

```bash
docker exec -it symfony_api_app ls -la /var/www/var/
```

## API Endpoints

### Upload de Arquivo
```
POST /api/upload-ods
Content-Type: multipart/form-data

Parâmetro: file (arquivo .ods)
```

### Status do Upload
```
GET /api/upload-status
```

### Validação de Informações
```
POST /api/validate-file-info
Content-Type: application/json

{
  "filename": "arquivo.ods"
}
```

## Exemplo de Resposta da API

### Sucesso
```json
{
  "success": true,
  "data": [
    {
      "row_number": 2,
      "data": {
        "A": "Valor 1",
        "B": "Valor 2",
        "C": "2024-01-15"
      },
      "validation": {
        "is_valid": true,
        "errors": [],
        "warnings": []
      }
    }
  ],
  "total_rows": 1,
  "errors": [],
  "summary": {
    "total_rows": 1,
    "valid_rows": 1,
    "rows_with_errors": 0,
    "rows_with_warnings": 0,
    "success_rate": 100.0
  }
}
```

### Erro
```json
{
  "success": false,
  "error": "Aba \"XX) Onglet technique - TOTAL\" não encontrada"
}
```

## Interface Web

Acesse `http://localhost:8080/upload.html` para testar o upload via interface web.

## Validações Implementadas

### Validações de Arquivo
- Extensão `.ods` obrigatória
- Tamanho máximo: 20MB
- MIME types válidos para ODS

### Validações de Dados
- Campos vazios (geram avisos)
- Formato de datas (YYYY-MM-DD)
- Formato de emails
- Detecção automática de tipos de dados

## Performance

- ✅ Processamento em memória para arquivos pequenos/médios
- ✅ Streaming para arquivos grandes
- ✅ Cache de validações
- ✅ Processamento paralelo de linhas (quando aplicável)

## Customização

### Adicionar Novas Validações

Edite `src/Service/OdsProcessorService.php` no método `validateRowData()`:

```php
// Exemplo: validar CPF
if (stripos($header['name'], 'cpf') !== false) {
    if (!$this->isValidCPF($value)) {
        $validatedRow['validation']['errors'][] = [
            'field' => $header['name'],
            'column' => $column,
            'message' => 'CPF inválido'
        ];
        $validatedRow['validation']['is_valid'] = false;
    }
}
```

### Modificar Aba Alvo

Altere o nome da aba no método `findWorksheet()`:

```php
private function findWorksheet(Spreadsheet $spreadsheet, string $sheetName): ?Worksheet
{
    // Modifique para buscar outra aba
    $targetSheet = 'Nome da sua aba';
    // ...
}
```

## Troubleshooting

### Erro: "No such file or directory"
```bash
# Verificar se diretórios existem
docker exec -it symfony_api_app mkdir -p /var/www/var/output /var/www/var/uploads
docker exec -it symfony_api_app chown -R www-data:www-data /var/www/var
```

### Erro: "Aba não encontrada"
- Verifique se o nome da aba está exatamente como "XX) Onglet technique - TOTAL"
- O sistema busca por correspondência parcial (case insensitive)

### Performance Lenta
- Arquivos muito grandes (>10MB) podem precisar de otimização
- Considere processamento assíncrono para arquivos grandes

## Dependências

- PHP 8.2+
- Symfony 6.4
- PhpSpreadsheet 1.29+
- Extensões PHP: zip, dom, simplexml, xsl

## Licença

Projeto proprietário.
