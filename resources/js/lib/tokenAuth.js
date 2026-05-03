const TOKEN_STORAGE_KEY = "velo.auth.token";
const COLLECTION_STORAGE_KEY = "velo.auth.collection";

export const getAuthToken = () => {
  return window.localStorage.getItem(TOKEN_STORAGE_KEY);
};

export const setAuthToken = (token) => {
  if (!token) {
    return;
  }

  window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
};

export const clearAuthToken = () => {
  window.localStorage.removeItem(TOKEN_STORAGE_KEY);
};

export const getAuthCollection = () => {
  return window.localStorage.getItem(COLLECTION_STORAGE_KEY);
};

export const setAuthCollection = (collectionName) => {
  if (!collectionName) {
    return;
  }

  window.localStorage.setItem(COLLECTION_STORAGE_KEY, collectionName);
};

export const clearAuthCollection = () => {
  window.localStorage.removeItem(COLLECTION_STORAGE_KEY);
};

export const applyAuthHeader = (axiosInstance, token = getAuthToken()) => {
  if (!axiosInstance) {
    return;
  }

  if (token) {
    axiosInstance.defaults.headers.common.Authorization = `Bearer ${token}`;
    return;
  }

  delete axiosInstance.defaults.headers.common.Authorization;
};
