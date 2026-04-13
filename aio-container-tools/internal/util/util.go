package util

import (
	"fmt"
	"log"
	"os"
)

var debugEnabled bool

// SetDebug enables or disables debug output.
func SetDebug(enabled bool) {
	debugEnabled = enabled
}

// Debugf prints a formatted debug message to stdout when debug mode is enabled.
func Debugf(format string, args ...any) {
	if debugEnabled {
		fmt.Printf("[debug] "+format+"\n", args...)
	}
}

// RequireEnv returns the value of the named environment variable.
// It writes an error to stderr and exits with code 1 if the variable is unset or empty.
func RequireEnv(key string) string {
	v := os.Getenv(key)
	if v == "" {
		fmt.Fprintf(os.Stderr, "required environment variable %q is not set\n", key)
		os.Exit(1)
	}
	Debugf("env %s = %q", key, v)
	return v
}

// OptionalEnv returns the value of the named environment variable, or fallback if it is unset or empty.
func OptionalEnv(key, fallback string) string {
	v := os.Getenv(key)
	if v == "" {
		Debugf("env %s unset, using fallback %q", key, fallback)
		return fallback
	}
	Debugf("env %s = %q", key, v)
	return v
}

// ErrorOut logs the error with a standard prefix and exits with code 1.
func ErrorOut(err error) {
	log.Fatalf("error: %v", err)
}
