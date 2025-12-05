@echo off
echo ========================================
echo ABRINDO PORTA 8002 NO FIREWALL
echo ========================================
echo.
echo Este script precisa ser executado como ADMINISTRADOR
echo Clique com botao direito e escolha "Executar como administrador"
echo.
pause

netsh advfirewall firewall add rule name="Laravel Dev Server 8002" dir=in action=allow protocol=TCP localport=8002 profile=any

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCESSO! Porta 8002 liberada no firewall
    echo ========================================
    echo.
    echo Agora voce pode acessar de outras maquinas:
    echo http://10.0.3.9:8002
    echo.
) else (
    echo.
    echo ========================================
    echo ERRO! Execute este arquivo como ADMINISTRADOR
    echo ========================================
    echo.
    echo 1. Clique com botao direito neste arquivo
    echo 2. Escolha "Executar como administrador"
    echo.
)

pause
