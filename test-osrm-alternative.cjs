// Teste de servidores OSRM alternativos GRATUITOS
// Executa com: node test-osrm-alternative.cjs

const https = require('https');

const coordinates = '-43.9386,-19.9191;-43.9450,-19.9227';

// Lista de servidores OSRM públicos/gratuitos
const servers = [
    {
        name: 'OSRM Demo (router.project-osrm.org)',
        hostname: 'router.project-osrm.org',
        path: `/route/v1/driving/${coordinates}?overview=full&geometries=geojson`
    },
    {
        name: 'OpenStreetMap.de OSRM',
        hostname: 'routing.openstreetmap.de',
        path: `/routed-car/route/v1/driving/${coordinates}?overview=full&geometries=geojson`
    }
];

function testServer(server) {
    return new Promise((resolve) => {
        console.log(`🧪 Testando ${server.name}...`);
        console.log(`   URL: https://${server.hostname}${server.path}`);

        const options = {
            hostname: server.hostname,
            port: 443,
            path: server.path,
            method: 'GET',
            timeout: 10000,
            headers: {
                'User-Agent': 'NDD-Transport-System/1.0'
            }
        };

        const req = https.request(options, (res) => {
            let data = '';

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

                        console.log(`   ✅ FUNCIONOU! ${distance} km, ${duration} min`);
                        console.log('');
                        resolve({ success: true, server });
                    } else {
                        console.log(`   ❌ Falhou: ${result.message || 'resposta inválida'}`);
                        console.log('');
                        resolve({ success: false, error: result });
                    }
                } catch (error) {
                    console.log(`   ❌ Erro: ${error.message}`);
                    console.log('');
                    resolve({ success: false, error: error.message });
                }
            });
        });

        req.on('error', (error) => {
            console.log(`   ❌ Erro de rede: ${error.message}`);
            console.log('');
            resolve({ success: false, error: error.message });
        });

        req.on('timeout', () => {
            req.destroy();
            console.log(`   ❌ Timeout (10s)`);
            console.log('');
            resolve({ success: false, error: 'timeout' });
        });

        req.end();
    });
}

async function testAll() {
    console.log('🧪 Testando servidores OSRM GRATUITOS...');
    console.log('');

    for (const server of servers) {
        const result = await testServer(server);

        if (result.success) {
            console.log('✅ SUCESSO! Servidor funcionando:', server.name);
            console.log('');
            console.log('💡 SOLUÇÃO PARA O CÓDIGO:');
            console.log('   Use L.Routing.osrmv1() com serviceUrl customizada:');
            console.log('');
            console.log(`   const router = L.Routing.osrmv1({`);
            console.log(`       serviceUrl: 'https://${server.hostname}${server.path.replace(coordinates, '').replace('?overview=full&geometries=geojson', '')}',`);
            console.log(`       profile: 'driving'`);
            console.log(`   });`);
            console.log('');
            process.exit(0);
        }
    }

    console.log('❌ Nenhum servidor OSRM público está funcionando no momento');
    console.log('');
    console.log('💡 ALTERNATIVAS:');
    console.log('   1. Tentar em outro horário (OSRM público é instável)');
    console.log('   2. Usar apenas os pontos sem rota (fallback)');
    console.log('   3. Implementar retry logic no frontend');
    process.exit(1);
}

testAll();
