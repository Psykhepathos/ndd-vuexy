#!/bin/bash

# Script de diagnóstico para problemas de impressão
# Execute no servidor onde o Flask está rodando: bash diagnostico_impressora.sh

echo "=========================================="
echo "DIAGNÓSTICO DE IMPRESSORA - transp4"
echo "=========================================="
echo ""

IMPRESSORA="transp4"

echo "1. Verificando se impressora existe..."
lpstat -p $IMPRESSORA
echo ""

echo "2. Verificando se impressora está aceitando trabalhos..."
lpstat -a $IMPRESSORA
echo ""

echo "3. Verificando trabalhos na fila..."
lpstat -o $IMPRESSORA
echo ""

echo "4. Verificando trabalhos detalhados..."
lpstat -o $IMPRESSORA -l | head -50
echo ""

echo "5. Verificando status geral do CUPS..."
systemctl status cups | head -20
echo ""

echo "6. Verificando últimas linhas do log de erros do CUPS..."
tail -30 /var/log/cups/error_log
echo ""

echo "7. Verificando últimas linhas do log de acesso do CUPS..."
tail -20 /var/log/cups/access_log
echo ""

echo "8. Testando impressão de página de teste..."
echo "Enviando arquivo de teste..."
echo "TESTE DE IMPRESSAO - $(date)" > /tmp/teste_impressao.txt
lp -d $IMPRESSORA /tmp/teste_impressao.txt
echo ""
sleep 2
echo "Verificando se apareceu na fila..."
lpstat -o $IMPRESSORA | grep -i teste
echo ""

echo "=========================================="
echo "AÇÕES RECOMENDADAS:"
echo "=========================================="
echo ""
echo "Se a impressora estiver PAUSADA:"
echo "  cupsenable $IMPRESSORA"
echo "  cupsaccept $IMPRESSORA"
echo ""
echo "Se a fila estiver travada, limpar tudo:"
echo "  cancel -a $IMPRESSORA"
echo ""
echo "Se CUPS estiver com problemas, reiniciar:"
echo "  sudo systemctl restart cups"
echo ""
echo "Para ver trabalhos em tempo real:"
echo "  watch -n 2 'lpstat -o $IMPRESSORA'"
echo ""
echo "=========================================="
