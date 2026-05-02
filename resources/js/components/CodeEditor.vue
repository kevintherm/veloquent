<script setup>
import { onMounted, ref, watch, onBeforeUnmount } from 'vue'
import { EditorView, basicSetup } from 'codemirror'
import { html } from '@codemirror/lang-html'
import { EditorState } from '@codemirror/state'
import { keymap } from '@codemirror/view'
import { indentWithTab } from '@codemirror/commands'
import {
  Type,
  ChevronDown,
  Copy,
  Check
} from 'lucide-vue-next'
import { Button } from '@/components/ui'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from '@/components/ui'
import { oneDark } from '@codemirror/theme-one-dark'
import { useTheme } from '@/lib/theme'
import { Compartment } from '@codemirror/state'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Write your code here...',
  },
  placeholders: {
    type: Array,
    default: () => [],
  },
  language: {
    type: String,
    default: 'html',
  }
})

const emit = defineEmits(['update:modelValue'])

const { isDark } = useTheme()
const editorContainer = ref(null)
const copied = ref(false)
let view = null
const themeConfig = new Compartment()

onMounted(() => {
  const startState = EditorState.create({
    doc: props.modelValue,
    extensions: [
      basicSetup,
      html(),
      EditorView.lineWrapping,
      keymap.of([indentWithTab]),
      EditorView.updateListener.of((update) => {
        if (update.docChanged) {
          emit('update:modelValue', update.state.doc.toString())
        }
      }),
      themeConfig.of(isDark.value ? oneDark : []),
      EditorView.theme({
        "&": { 
          height: "100%",
          backgroundColor: "transparent"
        },
        ".cm-scroller": { 
          overflow: "auto",
          fontFamily: "var(--font-mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace)"
        },
        "&.cm-focused": { outline: "none" },
        ".cm-gutters": {
          backgroundColor: "var(--muted)",
          color: "var(--muted-foreground)",
          borderRight: "1px solid var(--border)"
        },
        ".cm-activeLineGutter": {
          backgroundColor: "var(--accent)",
          color: "var(--accent-foreground)"
        },
        ".cm-content": {
          padding: "10px 0"
        }
      })
    ]
  })

  view = new EditorView({
    state: startState,
    parent: editorContainer.value
  })
})

watch(isDark, (dark) => {
  if (view) {
    view.dispatch({
      effects: themeConfig.reconfigure(dark ? oneDark : [])
    })
  }
})

watch(() => props.modelValue, (value) => {
  if (view && value !== view.state.doc.toString()) {
    view.dispatch({
      changes: { from: 0, to: view.state.doc.length, insert: value }
    })
  }
})

const insertPlaceholder = (code) => {
  if (!view) return
  const placeholder = `{{ ${code} }}`
  const { from, to } = view.state.selection.main
  view.dispatch({
    changes: { from, to, insert: placeholder },
    selection: { anchor: from + placeholder.length }
  })
  view.focus()
}

const copyToClipboard = () => {
  if (!view) return
  navigator.clipboard.writeText(view.state.doc.toString())
  copied.value = true
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

onBeforeUnmount(() => {
  if (view) {
    view.destroy()
  }
})
</script>

<template>
  <div class="border rounded-md overflow-hidden bg-background flex flex-col border-input h-full min-h-[inherit]">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-1 p-1 border-b border-input bg-muted/30">
      <div class="flex items-center px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70">
        {{ language }} Editor
      </div>

      <div class="w-px h-4 bg-input mx-1" />

      <!-- Placeholder Dropdown -->
      <DropdownMenu v-if="placeholders.length > 0">
        <DropdownMenuTrigger as-child>
          <Button type="button" variant="ghost" size="sm" class="h-7 px-2 gap-1 text-[11px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground border-transparent">
            <Type class="h-3.5 w-3.5" />
            Placeholders
            <ChevronDown class="h-3 w-3 opacity-50" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="bg-background border-input text-foreground">
          <DropdownMenuItem v-for="p in placeholders" :key="p.code" @click="insertPlaceholder(p.code)" class="focus:bg-muted focus:text-foreground cursor-pointer">
            <div class="flex flex-col gap-0.5">
              <span class="font-medium text-xs">{{ p.label }}</span>
              <span class="text-[10px] opacity-70 font-mono">&#123;&#123; {{ p.code }} &#125;&#125;</span>
            </div>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <div class="flex-1" />

      <Button type="button" variant="ghost" size="icon" class="h-7 w-7 text-muted-foreground hover:bg-muted hover:text-foreground" @click="copyToClipboard">
        <Check v-if="copied" class="h-3.5 w-3.5 text-green-600" />
        <Copy v-else class="h-3.5 w-3.5" />
      </Button>
    </div>

    <!-- Editor Area -->
    <div ref="editorContainer" class="flex-1 text-sm relative h-full"></div>
  </div>
</template>

<style>
/* CodeMirror 6 standard styles for full height */
.cm-editor {
  height: 100%;
}
.cm-scroller {
  height: 100% !important;
}
</style>
