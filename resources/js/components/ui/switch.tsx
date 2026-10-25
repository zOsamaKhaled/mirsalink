import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * An on/off switch. The knob slides toward the end of the line when on,
 * so it reads the same way in Arabic and English.
 */
function Switch({
  checked,
  onCheckedChange,
  className,
  ...props
}: Omit<React.ComponentProps<"button">, "onChange"> & {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      data-state={checked ? "checked" : "unchecked"}
      onClick={() => onCheckedChange(!checked)}
      className={cn(
        "inline-flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50",
        checked ? "bg-primary" : "bg-input",
        className
      )}
      {...props}
    >
      <span
        className={cn(
          "size-5 rounded-full bg-white shadow-sm transition-transform",
          checked ? "ltr:translate-x-5 rtl:-translate-x-5" : "translate-x-0"
        )}
      />
    </button>
  )
}

export { Switch }
