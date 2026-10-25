export type TranslationNode =
    | string
    | TranslationNode[]
    | { [key: string]: TranslationNode };
