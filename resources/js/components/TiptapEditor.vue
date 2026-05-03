<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { Link } from '@tiptap/extension-link'
import { Color } from '@tiptap/extension-color'
import { TextStyle } from '@tiptap/extension-text-style'
import { Placeholder } from '@tiptap/extension-placeholder'
import { Highlight } from '@tiptap/extension-highlight'
import { Underline } from '@tiptap/extension-underline'
import { TextAlign } from '@tiptap/extension-text-align'
import { watch, onBeforeUnmount } from 'vue'
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  Strikethrough,
  List,
  ListOrdered,
  Undo,
  Redo,
  Link as LinkIcon,
  Type,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Highlighter,
  ChevronDown
} from 'lucide-vue-next'
import { Button } from '@/components/ui'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from '@/components/ui'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Write something...',
  },
  placeholders: {
    type: Array,
    default: () => [],
  }
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3],
      },
    }),
    Underline,
    Link.configure({
      openOnClick: false,
    }),
    TextStyle,
    Color,
    Highlight,
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
  ],
  editorProps: {
    attributes: {
      class: 'prose prose-sm sm:prose-base dark:prose-invert focus:outline-none max-w-none min-h-[150px] px-4 py-3 text-foreground',
    },
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
})

watch(() => props.modelValue, (value) => {
  if (!editor.value) return
  const isSame = editor.value.getHTML() === value
  if (isSame) return
  editor.value.commands.setContent(value, false)
})

const toggleLink = () => {
  const previousUrl = editor.value.getAttributes('link').href
  const url = window.prompt('URL', previousUrl)

  if (url === null) return
  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

const insertPlaceholder = (code) => {
  editor.value.chain().focus().insertContent(`{{ ${code} }}`).run()
}

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div v-if="editor" class="border rounded-md overflow-hidden bg-background flex flex-col">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-1 p-1 border-b bg-muted/50">
      <!-- Text Style Dropdown -->
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button type="button" variant="ghost" size="sm" class="h-8 px-2 gap-1 text-xs font-normal min-w-[100px] justify-between">
            <span v-if="editor.isActive('heading', { level: 1 })">Heading 1</span>
            <span v-else-if="editor.isActive('heading', { level: 2 })">Heading 2</span>
            <span v-else-if="editor.isActive('heading', { level: 3 })">Heading 3</span>
            <span v-else>Paragraph</span>
            <ChevronDown class="h-3 w-3 opacity-50" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="bg-background">
          <DropdownMenuItem @click="editor.chain().focus().setParagraph().run()">
            Paragraph
          </DropdownMenuItem>
          <DropdownMenuItem @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">
            Heading 1
          </DropdownMenuItem>
          <DropdownMenuItem @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
            Heading 2
          </DropdownMenuItem>
          <DropdownMenuItem @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
            Heading 3
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <div class="w-px h-4 bg-border mx-1" />

      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('bold') }"
        @click="editor.chain().focus().toggleBold().run()">
        <Bold class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('italic') }"
        @click="editor.chain().focus().toggleItalic().run()">
        <Italic class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('underline') }"
        @click="editor.chain().focus().toggleUnderline().run()">
        <UnderlineIcon class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('strike') }"
        @click="editor.chain().focus().toggleStrike().run()">
        <Strikethrough class="h-4 w-4" />
      </Button>

      <div class="w-px h-4 bg-border mx-1" />

      <Button type="button" variant="ghost" size="icon" class="h-8 w-8"
        :class="{ 'bg-accent': editor.isActive({ textAlign: 'left' }) }"
        @click="editor.chain().focus().setTextAlign('left').run()">
        <AlignLeft class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8"
        :class="{ 'bg-accent': editor.isActive({ textAlign: 'center' }) }"
        @click="editor.chain().focus().setTextAlign('center').run()">
        <AlignCenter class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8"
        :class="{ 'bg-accent': editor.isActive({ textAlign: 'right' }) }"
        @click="editor.chain().focus().setTextAlign('right').run()">
        <AlignRight class="h-4 w-4" />
      </Button>

      <div class="w-px h-4 bg-border mx-1" />

      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('bulletList') }"
        @click="editor.chain().focus().toggleBulletList().run()">
        <List class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('orderedList') }"
        @click="editor.chain().focus().toggleOrderedList().run()">
        <ListOrdered class="h-4 w-4" />
      </Button>

      <div class="w-px h-4 bg-border mx-1" />

      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('link') }"
        @click="toggleLink">
        <LinkIcon class="h-4 w-4" />
      </Button>

      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" :class="{ 'bg-accent': editor.isActive('highlight') }"
        @click="editor.chain().focus().toggleHighlight().run()">
        <Highlighter class="h-4 w-4" />
      </Button>

      <div class="w-px h-4 bg-border mx-1" />

      <!-- Placeholder Dropdown -->
      <DropdownMenu v-if="placeholders.length > 0">
        <DropdownMenuTrigger as-child>
          <Button type="button" variant="ghost" size="sm" class="h-8 px-2 gap-1 text-xs font-normal">
            <Type class="h-3 w-3" />
            Placeholders
            <ChevronDown class="h-3 w-3 opacity-50" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
          <DropdownMenuItem v-for="p in placeholders" :key="p.code" @click="insertPlaceholder(p.code)">
            <div class="flex flex-col gap-0.5">
              <span class="font-medium text-xs">{{ p.label }}</span>
              <span class="text-[10px] text-muted-foreground font-mono">&#123;&#123; {{ p.code }} &#125;&#125;</span>
            </div>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <div class="flex-1" />

      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="editor.chain().focus().undo().run()"
        :disabled="!editor.can().undo()">
        <Undo class="h-4 w-4" />
      </Button>
      <Button type="button" variant="ghost" size="icon" class="h-8 w-8" @click="editor.chain().focus().redo().run()"
        :disabled="!editor.can().redo()">
        <Redo class="h-4 w-4" />
      </Button>
    </div>

    <!-- Editor Area -->
    <div class="flex-1 overflow-y-auto bg-background min-h-[250px]">
      <EditorContent :editor="editor" />
    </div>
  </div>
</template>

<style>
/* Tiptap-specific overrides if needed */
.tiptap p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: var(--muted-foreground);
  opacity: 0.5;
  pointer-events: none;
  height: 0;
}

/* Ensure prose styling respect theme colors and spacing */
.tiptap {
  color: inherit;
  line-height: 1.4;
}

.tiptap :where(h1, h2, h3, h4, h5, h6, p, blockquote, pre, code, em, strong, li, ol, ul, a) {
  color: inherit !important;
  line-height: inherit;
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.tiptap a {
  color: var(--primary) !important;
  text-decoration: underline;
}
</style>
