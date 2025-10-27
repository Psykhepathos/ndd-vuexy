// Teste DIRETO do OSRM público (sem libraries)
// Executa com: node test-osrm-direct.cjs

const https = require('https');

// Coordenadas de teste (2 pontos em Belo Horizonte)
// Formato OSRM: lng,lat
const coordinates = '-43.9386,-19.9191;-43.9450,-19.9227';

const options = {
    hostname: 'router.project-osrm.org',
    port: 443,
    path: `/route/v1/driving/${coordinates}?overview=full&geometries=geojson`,
    method: 'GET',
    headers: {
        'User-Agent': 'NDD-Transport-System/1.0'
    }
};

console.log('🧪 Testando OSRM Demo Server (GRÁTIS)...');
console.log('📍 URL:', `https://${options.hostname}${options.path}`);
console.log('');

function testOSRM(attempt = 1, maxAttempts = 5) {
    console.log(`Tentativa ${attempt}/${maxAttempts}...`);

    const req = https.request(options, (res) => {
        let data = '';

        console.log(`✓ Status Code: ${res.statusCode}`);

        res.on('data', (chunk) => {
            data += chunk;
        });

        res.on('end', () => {
            try {
                const result = JSON.parse(data);

                if (res.statusCode === 200 && result.code === 'Ok' && result.routes && result.routes.length > 0) {
                    const route = result.routes[0];
                    const distance = (route.distance / 1000).toFixed(1);
                    const duration = Math.round(route.duration / 60);

                    console.log('');
                    console.log('✅ SUCESSO! OSRM Demo Server funcionando!');
                    console.log('');
                    console.log('📊 Resultado:');
                    console.log(`   Distância: ${distance} km`);
                    console.log(`   Tempo: ${duration} min`);
                    console.log(`   Pontos na rota: ${route.geometry.coordinates.length}`);
                    console.log('');
                    console.log('✅ TESTE PASSOU - OSRM público está FUNCIONANDO!');
                    console.log('');
                    console.log('💡 SOLUÇÃO: Usar L.Routing.control() SEM especificar router');
                    console.log('   Ele usa OSRM por padrão, igual ao TransFlow');
                    console.log('');
                    console.log('💡 ESTRATÉGIA: Implementar retry logic para lidar com instabilidade');
                    process.exit(0);
                } else {
                    console.error('❌ ERRO na resposta:', result);

                    if (attempt < maxAttempts) {
                        console.log(`⏳ Aguardando 2s antes de tentar novamente...`);
                        console.log('');
                        setTimeout(() => testOSRM(attempt + 1, maxAttempts), 2000);
                    } else {
                        console.error('');
                        console.error('❌ TESTE FALHOU após', maxAttempts, 'tentativas');
                        console.error('💡 OSRM público está instável no momento');
                        process.exit(1);
                    }
                }
            } catch (error) {
                console.error('❌ ERRO ao parsear resposta:', error.message);

                if (attempt < maxAttempts) {
                    console.log(`⏳ Aguardando 2s antes de tentar novamente...`);
                    console.log('');
                    setTimeout(() => testOSRM(attempt + 1, maxAttempts), 2000);
                } else {
                    console.error('');
                    console.error('❌ TESTE FALHOU - Erro ao processar resposta');
                    process.exit(1);
                }
            }
        });
    });

    req.on('error', (error) => {
        console.error(`❌ ERRO na requisição: ${error.message}`);

        if (attempt < maxAttempts) {
            console.log(`⏳ Aguardando 2s antes de tentar novamente...`);
            console.log('');
            setTimeout(() => testOSRM(attempt + 1, maxAttempts), 2000);
        } else {
            console.error('');
            console.error('❌ TESTE FALHOU - Erro de rede persistente');
            console.error('💡 OSRM público pode estar bloqueando este IP');
            process.exit(1);
        }
    });

    req.end();
}

// Iniciar teste
testOSRM();
