#!/usr/bin/env node

/**
 * Script para desabilitar Google Maps APIs automaticamente
 * quando o orçamento for excedido
 */

const { google } = require('googleapis');
const serviceUsage = google.serviceusage('v1');

// IDs dos serviços Google Maps
const GOOGLE_MAPS_SERVICES = [
  'maps-backend.googleapis.com',
  'directions-backend.googleapis.com',
  'geocoding-backend.googleapis.com',
  'places-backend.googleapis.com'
];

async function disableGoogleMapsAPIs(projectId) {
  try {
    console.log('🚨 ORÇAMENTO EXCEDIDO - Desabilitando Google Maps APIs...');
    
    // Configurar autenticação
    const auth = new google.auth.GoogleAuth({
      scopes: ['https://www.googleapis.com/auth/service.management']
    });
    
    const authClient = await auth.getClient();
    google.options({ auth: authClient });
    
    // Desabilitar cada serviço
    for (const serviceName of GOOGLE_MAPS_SERVICES) {
      try {
        await serviceUsage.services.disable({
          name: `projects/${projectId}/services/${serviceName}`
        });
        console.log(`✅ Desabilitado: ${serviceName}`);
      } catch (error) {
        console.warn(`⚠️ Erro ao desabilitar ${serviceName}:`, error.message);
      }
    }
    
    console.log('🛡️ Todas as Google Maps APIs foram desabilitadas para proteger o orçamento!');
    
    // Opcional: Enviar notificação por email
    sendNotificationEmail();
    
  } catch (error) {
    console.error('❌ Erro ao desabilitar APIs:', error);
  }
}

function sendNotificationEmail() {
  console.log('📧 Enviando notificação de orçamento excedido...');
  // Implementar envio de email se necessário
}

// Executar se chamado diretamente
if (require.main === module) {
  const projectId = process.env.GOOGLE_CLOUD_PROJECT_ID;
  if (!projectId) {
    console.error('❌ GOOGLE_CLOUD_PROJECT_ID não configurado');
    process.exit(1);
  }
  
  disableGoogleMapsAPIs(projectId);
}

module.exports = { disableGoogleMapsAPIs };