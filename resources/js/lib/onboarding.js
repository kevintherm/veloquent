import axios from "axios";

export const isOnboardingInitialized = async () => {
  const response = await axios.post("/api/onboarding/initialized");
  return Boolean(response?.data?.data);
};
