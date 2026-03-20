import {
    AlignLeft,
    Binary,
    CalendarClock,
    Hash,
    Link2,
    ListTree,
    Mail,
    Type,
    WholeWord,
} from "lucide-vue-next";

const fieldTypeIcons = {
    text: Type,
    longtext: AlignLeft,
    number: Hash,
    boolean: Binary,
    timestamp: CalendarClock,
    email: Mail,
    url: Link2,
    json: WholeWord,
    relation: ListTree,
};

export const resolveCollectionFieldTypeIcon = (fieldType) => {
    return fieldTypeIcons[fieldType] ?? Type;
};
