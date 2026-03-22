import { cva } from "class-variance-authority";

export { default as Sheet } from "./Sheet.vue";
export { default as SheetClose } from "./SheetClose.vue";
export { default as SheetContent } from "./SheetContent.vue";
export { default as SheetDescription } from "./SheetDescription.vue";
export { default as SheetFooter } from "./SheetFooter.vue";
export { default as SheetHeader } from "./SheetHeader.vue";
export { default as SheetTitle } from "./SheetTitle.vue";
export { default as SheetTrigger } from "./SheetTrigger.vue";

export { default as Card } from "./Card.vue";
export { default as CardContent } from "./CardContent.vue";
export { default as CardDescription } from "./CardDescription.vue";
export { default as CardHeader } from "./CardHeader.vue";
export { default as CardTitle } from "./CardTitle.vue";

export { default as Tabs } from "./Tabs.vue";
export { default as TabsContent } from "./TabsContent.vue";
export { default as TabsList } from "./TabsList.vue";
export { default as TabsTrigger } from "./TabsTrigger.vue";

export { default as Button } from "./Button.vue";
export { default as Input } from "./Input.vue";
export { default as Label } from "./Label.vue";
export { default as Checkbox } from "./Checkbox.vue";
export { default as Separator } from "./Separator.vue";
export { default as Switch } from "./Switch.vue";
export { default as Skeleton } from "./skeleton/Skeleton.vue";

export { default as Table } from "./Table.vue";
export { default as TableBody } from "./TableBody.vue";
export { default as TableCell } from "./TableCell.vue";
export { default as TableHead } from "./TableHead.vue";
export { default as TableHeader } from "./TableHeader.vue";
export { default as TableRow } from "./TableRow.vue";

export { default as AlertDialog } from "./alert-dialog/AlertDialog.vue";
export { default as AlertDialogAction } from "./alert-dialog/AlertDialogAction.vue";
export { default as AlertDialogCancel } from "./alert-dialog/AlertDialogCancel.vue";
export { default as AlertDialogContent } from "./alert-dialog/AlertDialogContent.vue";
export { default as AlertDialogDescription } from "./alert-dialog/AlertDialogDescription.vue";
export { default as AlertDialogFooter } from "./alert-dialog/AlertDialogFooter.vue";
export { default as AlertDialogHeader } from "./alert-dialog/AlertDialogHeader.vue";
export { default as AlertDialogTitle } from "./alert-dialog/AlertDialogTitle.vue";
export { default as AlertDialogTrigger } from "./alert-dialog/AlertDialogTrigger.vue";

export { default as Dialog } from "./dialog/Dialog.vue";
export { default as DialogClose } from "./dialog/DialogClose.vue";
export { default as DialogContent } from "./dialog/DialogContent.vue";
export { default as DialogDescription } from "./dialog/DialogDescription.vue";
export { default as DialogFooter } from "./dialog/DialogFooter.vue";
export { default as DialogHeader } from "./dialog/DialogHeader.vue";
export { default as DialogScrollContent } from "./dialog/DialogScrollContent.vue";
export { default as DialogTitle } from "./dialog/DialogTitle.vue";
export { default as DialogTrigger } from "./dialog/DialogTrigger.vue";

export { default as DropdownMenu } from "./dropdown-menu/DropdownMenu.vue";
export { default as DropdownMenuTrigger } from "./dropdown-menu/DropdownMenuTrigger.vue";
export { default as DropdownMenuContent } from "./dropdown-menu/DropdownMenuContent.vue";
export { default as DropdownMenuItem } from "./dropdown-menu/DropdownMenuItem.vue";
export { default as DropdownMenuSeparator } from "./dropdown-menu/DropdownMenuSeparator.vue";

export const sheetVariants = cva(
  "fixed z-50 gap-4 bg-background p-6 shadow-lg transition ease-in-out data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-300 data-[state=open]:duration-500",
  {
    variants: {
      side: {
        top: "inset-x-0 top-0 border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top",
        bottom:
          "inset-x-0 bottom-0 border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom",
        left: "inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm",
        right:
          "inset-y-0 right-0 h-full w-3/4 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm",
      },
    },
    defaultVariants: {
      side: "right",
    },
  },
);

export * from "./pagination";
