import { CheckIcon, ChevronsUpDownIcon, Loader2, XIcon } from "lucide-react"
import * as React from "react"

import { Button } from "@/components/ui/button"
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { cn } from "@/lib/utils"

export type ComboboxOption = {
  value: string
  label: string
  /** Secondary line rendered under the label. */
  description?: string
  /** Extra terms the search should match on top of the label. */
  keywords?: string[]
  disabled?: boolean
}

/**
 * One option per value.
 *
 * cmdk keys every item by its value, so two options sharing one collide: the
 * list showed the same rider two or three times, and a click could land on the
 * copy that was missing the `disabled` flag - letting an option a guard had
 * ruled out be picked anyway. Collapsing them means a duplicate in the source
 * data can never defeat a validation rule.
 *
 * A disabled duplicate wins: if any copy says the option cannot be used, it
 * cannot be used, and its reason is the one shown.
 */
export function dedupeOptions(options: ComboboxOption[]): ComboboxOption[] {
  const byValue = new Map<string, ComboboxOption>()

  for (const option of options) {
    const existing = byValue.get(option.value)

    if (!existing) {
      byValue.set(option.value, option)
      continue
    }

    if (option.disabled && !existing.disabled) {
      byValue.set(option.value, {
        ...existing,
        disabled: true,
        description: option.description ?? existing.description,
      })
    }
  }

  return [...byValue.values()]
}

export interface ComboboxProps {
  options: ComboboxOption[]
  value?: string
  onChange: (value: string) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  /** Show an inline clear button once a value is picked. */
  clearable?: boolean
  loading?: boolean
  disabled?: boolean
  invalid?: boolean
  id?: string
  /** Renders a hidden input so the value is part of a native form submit. */
  name?: string
  className?: string
  contentClassName?: string
  /** Search is handled by the caller (server-side lookups) when provided. */
  onSearchChange?: (search: string) => void
}

function Combobox({
  options,
  value,
  onChange,
  placeholder = "Select an option",
  searchPlaceholder = "Search...",
  emptyText = "No results found.",
  clearable = false,
  loading = false,
  disabled = false,
  invalid = false,
  id,
  name,
  className,
  contentClassName,
  onSearchChange,
}: ComboboxProps) {
  const [open, setOpen] = React.useState(false)
  const items = React.useMemo(() => dedupeOptions(options), [options])
  const selected = items.find((option) => option.value === value)

  const select = (next: string) => {
    // Defence in depth: never commit a value the caller disabled.
    if (items.find((option) => option.value === next)?.disabled) {
      return
    }

    onChange(next === value ? "" : next)
    setOpen(false)
  }

  return (
    <>
      {name ? <input type="hidden" name={name} value={value ?? ""} /> : null}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            id={id}
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            aria-invalid={invalid || undefined}
            disabled={disabled}
            data-slot="combobox-trigger"
            className={cn(
              "h-9 w-full justify-between px-3 font-normal",
              !selected && "text-muted-foreground",
              className
            )}
          >
            <span className="truncate">{selected?.label ?? placeholder}</span>
            <span className="flex shrink-0 items-center gap-1">
              {clearable && selected && !disabled ? (
                <span
                  role="button"
                  tabIndex={-1}
                  aria-label="Clear selection"
                  className="hover:text-foreground text-muted-foreground rounded-sm"
                  onPointerDown={(event) => {
                    event.preventDefault()
                    event.stopPropagation()
                    onChange("")
                  }}
                >
                  <XIcon className="size-4" />
                </span>
              ) : null}
              <ChevronsUpDownIcon className="size-4 opacity-50" />
            </span>
          </Button>
        </PopoverTrigger>
        <PopoverContent
          align="start"
          className={cn(
            "w-(--radix-popover-trigger-width) p-0",
            contentClassName
          )}
        >
          <Command shouldFilter={!onSearchChange}>
            <CommandInput
              placeholder={searchPlaceholder}
              onValueChange={onSearchChange}
            />
            <CommandList>
              {loading ? (
                <div className="text-muted-foreground flex items-center justify-center gap-2 py-6 text-sm">
                  <Loader2 className="size-4 animate-spin" />
                  Loading...
                </div>
              ) : (
                <>
                  <CommandEmpty>{emptyText}</CommandEmpty>
                  <CommandGroup>
                    {items.map((option) => (
                      <CommandItem
                        key={option.value}
                        value={option.value}
                        keywords={[option.label, ...(option.keywords ?? [])]}
                        disabled={option.disabled}
                        onSelect={select}
                      >
                        <CheckIcon
                          className={cn(
                            "size-4",
                            option.value === value
                              ? "opacity-100"
                              : "opacity-0"
                          )}
                        />
                        <span className="flex min-w-0 flex-col">
                          <span className="truncate">{option.label}</span>
                          {option.description ? (
                            <span className="text-muted-foreground truncate text-xs">
                              {option.description}
                            </span>
                          ) : null}
                        </span>
                      </CommandItem>
                    ))}
                  </CommandGroup>
                </>
              )}
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </>
  )
}

export { Combobox }
