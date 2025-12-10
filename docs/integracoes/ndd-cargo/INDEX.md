# Integracao NDD Cargo - Indice

**Versao:** 3.1.0
**Ultima Atualizacao:** 2025-12-10
**Status:** Completo (Backend + Frontend)

---

## Navegacao Rapida

| Documento | Descricao |
|-----------|-----------|
| [README.md](README.md) | Visao geral da integracao |
| [IMPLEMENTACAO_BACKEND.md](IMPLEMENTACAO_BACKEND.md) | Backend NDD Cargo |
| [VPO_DATA_SYNC.md](VPO_DATA_SYNC.md) | Sistema de sincronizacao |
| [VPO_EMISSAO_WIZARD.md](VPO_EMISSAO_WIZARD.md) | Wizard de emissao |
| [TABELA_MAPEAMENTO_VPO.md](TABELA_MAPEAMENTO_VPO.md) | Mapeamento Progress -> VPO |
| [API_REFERENCE.md](API_REFERENCE.md) | Referencia de endpoints |
| [VPO_FRONTEND_GUIDE.md](VPO_FRONTEND_GUIDE.md) | Guia frontend |
| [BUSINESS_LOGIC.md](BUSINESS_LOGIC.md) | Logica de negocio |

---

## Documentacao por Categoria

### Principal
- [README.md](README.md) - Visao geral e arquitetura

### Backend
- [IMPLEMENTACAO_BACKEND.md](IMPLEMENTACAO_BACKEND.md) - Roteirizador NDD Cargo
- [ANALISE_NTESTE_PY.md](ANALISE_NTESTE_PY.md) - Referencia Python original
- [ANALISE_RESULTADO_PY.md](ANALISE_RESULTADO_PY.md) - Consulta assincrona

### VPO Data Sync
- [VPO_DATA_SYNC.md](VPO_DATA_SYNC.md) - Sistema de sincronizacao
- [TABELA_MAPEAMENTO_VPO.md](TABELA_MAPEAMENTO_VPO.md) - Mapeamento campos
- [MAPEAMENTO_VPO_PROGRESS.md](MAPEAMENTO_VPO_PROGRESS.md) - Detalhes Progress
- [MODELO_EMISSAO_VPO.md](MODELO_EMISSAO_VPO.md) - Estrutura XML

### Frontend
- [VPO_EMISSAO_WIZARD.md](VPO_EMISSAO_WIZARD.md) - Wizard emissao
- [VPO_FRONTEND_GUIDE.md](VPO_FRONTEND_GUIDE.md) - Guia desenvolvimento
- [API_REFERENCE.md](API_REFERENCE.md) - Endpoints API
- [BUSINESS_LOGIC.md](BUSINESS_LOGIC.md) - Logica negocio

### Troubleshooting
- [VPO_VALIDACAO_IMPLEMENTADA.md](VPO_VALIDACAO_IMPLEMENTADA.md) - Validacoes
- [VPO_PROBLEMAS_ENCONTRADOS.md](VPO_PROBLEMAS_ENCONTRADOS.md) - Problemas conhecidos

---

## Endpoints Principais

### VPO Sync
```
GET  /api/vpo/test-connection
POST /api/vpo/sync/transportador
POST /api/vpo/sync/batch
GET  /api/vpo/transportadores
GET  /api/vpo/transportadores/{codtrn}
GET  /api/vpo/statistics
```

### VPO Emissao
```
POST /api/vpo/emissao/validate
POST /api/vpo/emissao/preview
POST /api/vpo/emissao/emit
GET  /api/vpo/emissao/motoristas/{codtrn}
```

### NDD Cargo
```
GET  /api/ndd-cargo/test-connection
POST /api/ndd-cargo/roteirizador/consultar
GET  /api/ndd-cargo/resultado/{guid}
```

---

## Arquivos do Sistema

### Controllers
- `app/Http/Controllers/Api/VpoController.php`
- `app/Http/Controllers/Api/VpoEmissaoController.php`
- `app/Http/Controllers/Api/NddCargoController.php`

### Services
- `app/Services/Vpo/VpoDataSyncService.php` (660 linhas)
- `app/Services/Vpo/VpoEmissaoService.php`
- `app/Services/Vpo/MotoristaEmpresaCacheService.php`
- `app/Services/NddCargo/NddCargoService.php`
- `app/Services/NddCargo/NddCargoSoapClient.php`
- `app/Services/NddCargo/DigitalSignature.php`

### Frontend
- `resources/ts/pages/vpo-emissao/nova.vue`
- `resources/ts/pages/vpo-emissao/index.vue`
- `resources/ts/pages/vpo-emissao/components/VpoStep1Pacote.vue`
- `resources/ts/pages/vpo-emissao/components/VpoStep2Motorista.vue`
- `resources/ts/pages/vpo-emissao/components/VpoStep3Veiculo.vue`
- `resources/ts/pages/vpo-emissao/components/VpoStep4Rota.vue`
- `resources/ts/pages/vpo-emissao/components/VpoStep5Confirmacao.vue`

---

## Estatisticas

| Metrica | Valor |
|---------|-------|
| Documentos | 15 |
| Codigo backend | ~4500 linhas |
| Codigo frontend | ~3000 linhas |
| Cobertura VPO | 100% (19/19 campos) |
| Transportadores | 6.913+ |

---

## Fluxo de Desenvolvimento

### Para Backend
```
1. README.md (visao geral)
2. IMPLEMENTACAO_BACKEND.md (detalhes)
3. VPO_DATA_SYNC.md (sync)
4. TABELA_MAPEAMENTO_VPO.md (campos)
```

### Para Frontend
```
1. BUSINESS_LOGIC.md (entender negocio)
2. VPO_FRONTEND_GUIDE.md (guia)
3. API_REFERENCE.md (endpoints)
4. VPO_EMISSAO_WIZARD.md (wizard)
```

---

## Regras Criticas

### Autonomo vs Empresa
```php
if ($transportador['flgautonomo']) {
    // Dados em PUB.transporte
    $condutor = $transportador['nomtrn'];
} else {
    // Motorista em PUB.trnmot
    $motorista = $this->getMotorista($codmot);
    $condutor = $motorista['nommot'];
}
```

### Processamento Assincrono
```
1. POST /api/ndd-cargo/roteirizador/consultar
   -> ResponseCode 202 + GUID

2. GET /api/ndd-cargo/resultado/{guid}
   -> Polling ate ResponseCode 200

3. Pracas enriquecidas com coordenadas ANTT
```

### Assinatura Digital
- Protocolo: CrossTalk SOAP 1.1
- Assinatura: RSA-SHA1 (XML Digital Signature)
- Certificado: .pfx em storage/certificates/nddcargo/

---

**Mantido por:** Psykhepathos
