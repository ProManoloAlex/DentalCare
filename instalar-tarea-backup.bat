@echo off
REM instalar-tarea-backup.bat
REM
REM Corre esto UNA SOLA VEZ (doble clic) para registrar la tarea programada
REM de Windows que ejecuta el backup diario automaticamente. No necesitas
REM editar nada: detecta solo donde esta PHP y donde esta este proyecto.

REM --- Detectar la ruta del proyecto automaticamente ---
REM %~dp0 es la carpeta donde vive ESTE .bat. Si lo pones en la raiz del
REM proyecto (junto a index.php), esto encuentra backup.php solo.
set RUTA_SCRIPT=%~dp0scripts\backup\backup.php

if not exist "%RUTA_SCRIPT%" (
    echo No se encontro scripts\backup\backup.php junto a este archivo .bat.
    echo Asegurate de poner este .bat en la carpeta raiz del proyecto ^(junto a index.php^).
    pause
    exit /b 1
)

REM --- Detectar php.exe automaticamente ---
where php >nul 2>nul
if %errorlevel%==0 (
    set RUTA_PHP=php
) else (
    REM No esta en el PATH del sistema, buscar en ubicaciones comunes de XAMPP/WAMP
    if exist "C:\xampp\php\php.exe" (
        set RUTA_PHP=C:\xampp\php\php.exe
    ) else if exist "C:\wamp64\bin\php\php.exe" (
        set RUTA_PHP=C:\wamp64\bin\php\php.exe
    ) else (
        echo No se pudo encontrar php.exe automaticamente.
        set /p RUTA_PHP="Escribe la ruta completa a tu php.exe: "
    )
)

REM --- Registrar la tarea ---
schtasks /create ^
  /tn "Backup DentalCare" ^
  /tr "\"%RUTA_PHP%\" \"%RUTA_SCRIPT%\"" ^
  /sc daily ^
  /st 03:00 ^
  /f

echo.
echo Listo. La tarea "Backup DentalCare" quedo instalada, corre todos los dias a las 3:00 a.m.
echo Para probarla ya mismo: schtasks /run /tn "Backup DentalCare"
pause
