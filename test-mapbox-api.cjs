// Teste direto da API Mapbox Directions (funciona com Leaflet Routing Machine)
// Executa com: node test-mapbox-api.cjs

const https = require('https');

const API_KEY = 'pk.eyJ1IjoicHN5a2hlcGF0aG9zIiwiYSI6ImNtNGlleXRrMjA5cmkybXM3dTZ3cjJrdmQifQ.Q3w7VJQM_5awMp7NKSXZ4A';

// Coordenadas de teste (2 pontos em Belo Horizonte)
// Formato Mapbox: lng,lat
const coordinates = '-43.9386,-19.9191;-43.9450,-19.9227';

const options = {
    hostname: 'api.mapbox.com',
    port: 443,
    path: `/directions/v5/mapbox/driving/${coordinates}?geometries=geojson&access_token=${API_KEY}`,
    method: 'GET'
};

console.log('🧪 Testando Mapbox Directions API...');
console.log('📍 Coordenadas: BH Ponto A → BH Ponto B');
console.log('');

const req = https.request(options, (res) => {
    let data = '';

    console.log(`✓ Status Code: ${res.statusCode}`);
    console.log('');

    res.on('data', (chunk) => {
        data += chunk;
    });

    res.on('end', () => {
        try {
            const result = JSON.parse(data);

            if (res.statusCode === 200 && result.routes && result.routes.length > 0) {
                const route = result.routes[0];
                const distance = (route.distance / 1000).toFixed(1);
                const duration = Math.round(route.duration / 60);

                console.log('✅ SUCESSO! Mapbox Directions funcionando!');
                console.log('');
                console.log('📊 Resultado:');
                console.log(`   Distância: ${distance} km`);
                console.log(`   Tempo: ${duration} min`);
                console.log(`   Pontos na rota: ${route.geometry.coordinates.length}`);
                console.log('');
                console.log('✅ TESTE PASSOU - Mapbox está funcionando corretamente!');
                console.log('');
                console.log('💡 CONCLUSÃO: Use Mapbox com Leaflet Routing Machine');
                console.log('   router: L.Routing.mapbox(API_KEY)');
                process.exit(0);
            } else {
                console.error('❌ ERRO:', result);
                console.error('');
                console.error('❌ TESTE FALHOU - Resposta inválida');
                process.exit(1);
            }
        } catch (error) {
            console.error('❌ ERRO ao parsear resposta:', error.message);
            console.error('Resposta bruta:', data.substring(0, 500));
            console.error('');
            console.error('❌ TESTE FALHOU - Erro ao processar resposta');
            process.exit(1);
        }
    });
});

req.on('error', (error) => {
    console.error('❌ ERRO na requisição:', error.message);
    console.error('');
    console.error('❌ TESTE FALHOU - Erro de rede');
    process.exit(1);
});

req.end();
