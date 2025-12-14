import { defineStore } from 'pinia';
import api from '../services/api';
import { initEcho, disconnectEcho } from '../services/echo';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async login(email, password) {
      const response = await api.post('/login', { email, password });
      this.user = response.data.user;
      this.token = response.data.token;
      localStorage.setItem('token', this.token);
      initEcho(this.token);
    },

    async logout() {
      try {
        await api.post('/logout');
      } catch (err) {
        console.error('Logout error:', err);
      }
      this.user = null;
      this.token = null;
      localStorage.removeItem('token');
      disconnectEcho();
    },

    async checkAuth() {
      if (this.token) {
        try {
          const response = await api.get('/profile');
          if (response.data) {
            initEcho(this.token);
          }
        } catch (err) {
          this.logout();
        }
      }
    },
  },
});