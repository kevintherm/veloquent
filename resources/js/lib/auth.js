import { reactive, readonly } from "vue";
import axios from "axios";
import {
  applyAuthHeader,
  clearAuthCollection,
  clearAuthToken,
  getAuthToken,
  setAuthCollection,
  setAuthToken,
} from "@/lib/tokenAuth";

const state = reactive({
  user: null,
  initialized: false,
});

const AUTH_COLLECTION = "superusers";

export const clearAuthSession = () => {
  state.user = null;
  state.initialized = true;
  clearAuthToken();
  clearAuthCollection();
  applyAuthHeader(axios, null);
  window.disconnectEcho?.();
};

export const useAuth = () => {
  const resolveCollection = () => {
    return AUTH_COLLECTION;
  };

  const login = async ({ identity, password }) => {
    const collection = resolveCollection();
    const response = await axios.post(`/api/collections/${collection}/auth/login`, {
      identity,
      password,
    });

    const token = response?.data?.data?.token;

    if (token) {
      setAuthToken(token);
      setAuthCollection(AUTH_COLLECTION);
      applyAuthHeader(axios, token);
      window.connectEcho?.();
    }

    return response;
  };

  const fetchUser = async () => {
    const token = getAuthToken();
    const collection = resolveCollection();

    if (!token) {
      state.user = null;
      state.initialized = true;

      return;
    }

    try {
      const response = await axios.get(`/api/collections/${collection}/auth/me`);
      state.user = response.data.data;
    } catch (error) {
      if (error?.response?.status === 401) {
        clearAuthSession();
      } else {
        state.user = null;
      }
    } finally {
      state.initialized = true;
    }
  };

  const logout = async () => {
    const collection = resolveCollection();

    try {
      await axios.delete(`/api/collections/${collection}/auth/logout`);
    } catch (error) {
      console.error("Logout error", error);
    } finally {
      clearAuthSession();
    }
  };

  return {
    state: readonly(state),
    login,
    fetchUser,
    logout,
  };
};
