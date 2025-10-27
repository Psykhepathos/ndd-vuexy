// Teste direto da API OpenRouteService
// Executa com: node test-openroute-api.js

const https = require('https');

const API_KEY = '5b3ce3597851110001cf6248b08a59258c184f5fab1b0c27a6c53cb7';

// Coordenadas de teste (2 pontos em Belo Horizonte)
const coordinates = [
    [-43.9386, -19.9191], // [lng, lat] Ponto A
    [-43.9450, -19.9227]  // [lng, lat] Ponto B (~5km de distância)
];

const postData = JSON.stringify({
    coordinates: coordinates
});

const options = {
    hostname: 'api.openrouteservice.org',
    port: 443,
    path: '/v2/directions/driving-car',
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': API_KEY,
        'Content-Length': Buffer.byteLength(postData)
    }
};

console.log('🧪 Testando OpenRouteService API...');
console.log('📍 Coordenadas:', coordinates);
console.log('');

const req = https.request(options, (res) => {
    let data = '';

    console.log(`✓ Status Code: ${res.statusCode}`);
    console.log(`✓ Headers:`, res.headers);
    console.log('');

    res.on('data', (chunk) => {
        data += chunk;
    });

    res.on('end', () => {
        try {
            const result = JSON.parse(data);

            if (res.statusCode === 200) {
                const route = result.routes[0];
                const distance = (route.summary.distance / 1000).toFixed(1);
                const duration = Math.round(route.summary.duration / 60);

                console.log('✅ SUCESSO! OpenRouteService funcionando!');
                console.log('');
                console.log('📊 Resultado:');
                console.log(`   Distância: ${distance} km`);
                console.log(`   Tempo: ${duration} min`);
                console.log(`   Pontos na rota: ${route.geometry.coordinates ? route.geometry.coordinates.length : 'N/A'}`);
                console.log('');
                console.log('✅ TESTE PASSOU - API OpenRouteService está funcionando corretamente!');
                process.exit(0);
            } else {
                console.error('❌ ERRO:', result);
                console.error('');
                console.error('❌ TESTE FALHOU - Status não é 200');
                process.exit(1);
            }
        } catch (error) {
            console.error('❌ ERRO ao parsear resposta:', error.message);
            console.error('Resposta bruta:', data);
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

req.write(postData);
req.end();
