<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "axios";
import { toast } from "vue-sonner";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  Input,
  Label,
  Button,
  Separator
} from "@/components/ui";

const loading = ref(false);
const saving = ref(false);
const testing = ref(false);
const errors = ref({});

const form = ref({
  ai: {
    ai_provider: "openai",
    ai_model: "gpt-4o-mini",
    ai_api_key: ""
  }
});

let isInitializing = false;

// Dynamic default models when provider changes
watch(
  () => form.value.ai.ai_provider,
  (newProvider) => {
    if (isInitializing) {
      return;
    }
    if (newProvider === "openai") {
      form.value.ai.ai_model = "gpt-4o-mini";
    } else if (newProvider === "gemini") {
      form.value.ai.ai_model = "gemini-1.5-flash";
    } else if (newProvider === "anthropic") {
      form.value.ai.ai_model = "claude-3-5-sonnet-20241022";
    } else if (newProvider === "deepseek") {
      form.value.ai.ai_model = "deepseek-chat";
    } else if (newProvider === "groq") {
      form.value.ai.ai_model = "llama-3.3-70b-versatile";
    } else if (newProvider === "ollama") {
      form.value.ai.ai_model = "llama3";
    } else if (newProvider === "openrouter") {
      form.value.ai.ai_model = "meta-llama/llama-3-70b-instruct";
    } else if (newProvider === "mistral") {
      form.value.ai.ai_model = "mistral-large-latest";
    } else if (newProvider === "xai") {
      form.value.ai.ai_model = "grok-2-1212";
    }
  }
);

const loadSettings = async () => {
  loading.value = true;
  isInitializing = true;
  errors.value = {};

  try {
    const { data } = await axios.get("settings");
    if (data?.data) {
      // Ensure ai settings sub-object is present
      if (data.data.ai) {
        form.value.ai = {
          ai_provider: data.data.ai.ai_provider || "openai",
          ai_model: data.data.ai.ai_model || "gpt-4o-mini",
          ai_api_key: data.data.ai.ai_api_key || ""
        };
      }
    }
  } catch (error) {
    toast.error("Failed to load AI settings.");
  } finally {
    loading.value = false;
    setTimeout(() => {
      isInitializing = false;
    }, 0);
  }
};

const saveSettings = async () => {
  saving.value = true;
  errors.value = {};

  try {
    const { data } = await axios.patch("settings", form.value);
    if (data?.data && data.data.ai) {
      isInitializing = true;
      form.value.ai = {
        ai_provider: data.data.ai.ai_provider || "openai",
        ai_model: data.data.ai.ai_model || "gpt-4o-mini",
        ai_api_key: data.data.ai.ai_api_key || ""
      };
    }
    toast.success("AI configuration saved successfully!");
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      toast.error(error.response?.data?.message || "Failed to save AI configuration.");
    }
  } finally {
    saving.value = false;
    setTimeout(() => {
      isInitializing = false;
    }, 0);
  }
};

const testConnection = async () => {
  testing.value = true;
  errors.value = {};

  try {
    const { data } = await axios.post("settings/test-ai", {
      ai_provider: form.value.ai.ai_provider,
      ai_model: form.value.ai.ai_model,
      ai_api_key: form.value.ai.ai_api_key
    });

    const responseText = data?.data?.response || "OK";
    toast.success(`AI Connection Verified! Response: "${responseText}"`);
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    }
    const errorMsg = error.response?.data?.message || "Connection verification failed.";
    toast.error(errorMsg);
  } finally {
    testing.value = false;
  }
};

onMounted(() => {
  loadSettings();
});
</script>

<template>
  <div v-if="loading" class="flex justify-center p-8">
    <p class="text-muted-foreground animate-pulse">Loading AI settings...</p>
  </div>

  <div v-else class="space-y-6">
    <Card>
      <CardHeader>
        <CardTitle>AI Configuration</CardTitle>
        <CardDescription>Configure the tenant-specific AI settings, including model, credentials, and API provider.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label>AI Provider</Label>
            <select v-model="form.ai.ai_provider"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
              <option value="openai">OpenAI</option>
              <option value="gemini">Google Gemini</option>
              <option value="anthropic">Anthropic Claude</option>
              <option value="deepseek">DeepSeek</option>
              <option value="groq">Groq</option>
              <!-- <option value="ollama">Ollama (Local)</option> -->
              <option value="openrouter">OpenRouter</option>
              <option value="mistral">Mistral</option>
              <option value="xai">xAI Grok</option>
            </select>
            <p v-if="errors['ai.ai_provider']" class="text-xs text-red-500">{{ errors['ai.ai_provider'][0] }}</p>
          </div>

          <div class="space-y-2">
            <Label>AI Model</Label>
            <Input v-model="form.ai.ai_model" placeholder="e.g. gpt-4o-mini, gemini-1.5-flash" />
            <p class="text-[11px] text-muted-foreground">
              Suggested models:
              <span v-if="form.ai.ai_provider === 'openai'" class="font-mono">gpt-4o-mini, gpt-4o, o1-mini</span>
              <span v-else-if="form.ai.ai_provider === 'gemini'" class="font-mono">gemini-1.5-flash,
                gemini-1.5-pro</span>
              <span v-else-if="form.ai.ai_provider === 'anthropic'" class="font-mono">claude-3-5-sonnet-20241022,
                claude-3-haiku-20240307</span>
              <span v-else-if="form.ai.ai_provider === 'deepseek'" class="font-mono">deepseek-chat,
                deepseek-reasoner</span>
              <span v-else-if="form.ai.ai_provider === 'groq'" class="font-mono">llama-3.3-70b-versatile,
                mixtral-8x7b-32768</span>
              <!-- <span v-else-if="form.ai.ai_provider === 'ollama'" class="font-mono">llama3, mistral, phi3</span> -->
              <span v-else-if="form.ai.ai_provider === 'openrouter'" class="font-mono">meta-llama/llama-3-70b-instruct,
                anthropic/claude-3.5-sonnet</span>
              <span v-else-if="form.ai.ai_provider === 'mistral'" class="font-mono">mistral-large-latest,
                pixtral-large-latest</span>
              <span v-else-if="form.ai.ai_provider === 'xai'" class="font-mono">grok-2-1212, grok-beta</span>
            </p>
            <p v-if="errors['ai.ai_model']" class="text-xs text-red-500">{{ errors['ai.ai_model'][0] }}</p>
          </div>
        </div>

        <div class="space-y-2">
          <Label>API Key</Label>
          <Input v-model="form.ai.ai_api_key" type="password" placeholder="••••••••" />
          <p v-if="errors['ai.ai_api_key']" class="text-xs text-red-500">{{ errors['ai.ai_api_key'][0] }}</p>
        </div>

        <Separator class="my-4" />

        <div class="flex justify-between items-center">
          <Button :disabled="saving" @click="saveSettings">
            {{ saving ? "Saving..." : "Save Configuration" }}
          </Button>
        </div>

      </CardContent>
    </Card>
  </div>
</template>
