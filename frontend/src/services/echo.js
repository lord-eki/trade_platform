import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;

export const initEcho = (token) => {
  echoInstance = new Echo({
    broadcaster: 'pusher',
    key: 'd324c57507ad924203fa',
    cluster: 'mt1',
    forceTLS: true,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    },
    authEndpoint: 'http://localhost:8000/broadcasting/auth',
  });
  
  return echoInstance;
};

export const getEcho = () => echoInstance;

export const disconnectEcho = () => {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
};