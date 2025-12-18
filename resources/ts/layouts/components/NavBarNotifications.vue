<script lang="ts" setup>
import type { Notification } from '@layouts/types'

import avatar3 from '@images/avatars/avatar-3.png'
import avatar4 from '@images/avatars/avatar-4.png'
import avatar5 from '@images/avatars/avatar-5.png'
import paypal from '@images/cards/paypal-rounded.png'

const notifications = ref<Notification[]>([
  {
    id: 1,
    img: avatar4,
    title: 'Parabéns Flora! 🎉',
    subtitle: 'Ganhou o selo de melhor vendedor do mês',
    time: 'Hoje',
    isSeen: true,
  },
  {
    id: 2,
    text: 'Tom Holland',
    title: 'Novo usuário registrado.',
    subtitle: '5 horas atrás',
    time: 'Ontem',
    isSeen: false,
  },
  {
    id: 3,
    img: avatar5,
    title: 'Nova mensagem recebida 👋🏻',
    subtitle: 'Você tem 10 mensagens não lidas',
    time: '11 Ago',
    isSeen: true,
  },
  {
    id: 4,
    img: paypal,
    title: 'PayPal',
    subtitle: 'Pagamento Recebido',
    time: '25 Mai',
    isSeen: false,
    color: 'error',
  },
  {
    id: 5,
    img: avatar3,
    title: 'Pedido Recebido 📦',
    subtitle: 'Novo pedido recebido de João',
    time: '19 Mar',
    isSeen: true,
  },
])

const removeNotification = (notificationId: number) => {
  notifications.value.forEach((item, index) => {
    if (notificationId === item.id)
      notifications.value.splice(index, 1)
  })
}

const markRead = (notificationId: number[]) => {
  notifications.value.forEach(item => {
    notificationId.forEach(id => {
      if (id === item.id)
        item.isSeen = true
    })
  })
}

const markUnRead = (notificationId: number[]) => {
  notifications.value.forEach(item => {
    notificationId.forEach(id => {
      if (id === item.id)
        item.isSeen = false
    })
  })
}

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isSeen)
    markRead([notification.id])
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
