import {
    AlignLeft,
    Binary,
    Calendar,
    CalendarClock,
    FileText,
    Hash,
    Link2,
    ListTree,
    Mail,
    Paperclip,
    Type,
    WholeWord,
} from "lucide-vue-next";

const fieldTypeIcons = {
    text: Type,
    longtext: AlignLeft,
    richtext: FileText,
    number: Hash,
    boolean: Binary,
    datetime: CalendarClock,
    date: Calendar,
    email: Mail,
    url: Link2,
    json: WholeWord,
    file: Paperclip,
    relation: ListTree,
};

export const resolveCollectionFieldTypeIcon = (fieldType) => {
    return fieldTypeIcons[fieldType] ?? Type;
};
