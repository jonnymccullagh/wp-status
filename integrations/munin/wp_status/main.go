package main

import (
    "encoding/json"
    "flag"
    "fmt"
    "io/ioutil"
    "net/http"
    "os"
)

// WPStatus holds the data structure of the JSON response
type WPStatus struct {
    Status                string  `json:"status"`
    WPStatusCode          int     `json:"wp_status_code"`
    DatabaseAccess        int     `json:"database_access"`
    PluginUpdateCount     int     `json:"plugin_update_count"`
    ThemeUpdateCount      int     `json:"theme_update_count"`
    CoreUpdateAvailable   bool    `json:"core_update_available"`
    UnapprovedComments    int     `json:"unapproved_comments"`
    ResponseTimeMs        float64 `json:"response_time_ms"`
    CurrentScriptMemoryMb float64 `json:"current_script_memory_mb"`
    PeakScriptMemoryMb    float64 `json:"peak_script_memory_mb"`
    WPVersion             string  `json:"wp_version"`
    PHPVersion            string  `json:"php_version"`
    DBQueryCount          int     `json:"db_query_count"`
}

func printConfig() {
    fmt.Println("graph_title WP Status")
    fmt.Println("graph_vlabel count")
    fmt.Println("graph_category wordpress")
    fmt.Println("plugin_update_count.label Plugin Updates")
    fmt.Println("theme_update_count.label Theme Updates")
    fmt.Println("core_update_available.label Core Update Available")
    fmt.Println("unapproved_comments.label Unapproved Comments")
    fmt.Println("response_time_ms.label Response Time (ms)")
    fmt.Println("peak_script_memory_mb.label Peak Script Memory (MB)")
    fmt.Println("db_query_count.label DB Query Count")
}

func fetchData(url, password string) (WPStatus, error) {
    client := &http.Client{}
    req, err := http.NewRequest("GET", url, nil)
    if err != nil {
        return WPStatus{}, err
    }

    if password != "" {
        req.Header.Add("Authorization", password)
    }

    resp, err := client.Do(req)
    if err != nil {
        return WPStatus{}, err
    }
    defer resp.Body.Close()

    body, err := ioutil.ReadAll(resp.Body)
    if err != nil {
        return WPStatus{}, err
    }

    var wpStatus WPStatus
    err = json.Unmarshal(body, &wpStatus)
    if err != nil {
        return WPStatus{}, err
    }

    return wpStatus, nil
}

func main() {
    url := flag.String("H", "", "URL of the endpoint to check")
    password := flag.String("P", "", "Password for Authorization header (optional)")
    flag.Parse()

    if len(os.Args) > 1 && os.Args[1] == "config" {
        printConfig()
        os.Exit(0)
    }

    if *url == "" || *password == "" {
        fmt.Println("ERROR: The -H (URL) and -P (password) parameters are required.")
        os.Exit(3)
    }

    wpStatus, err := fetchData(*url, *password)
    if err != nil {
        fmt.Printf("Error fetching data: %s\n", err)
        os.Exit(2)
    }

    fmt.Printf("plugin_update_count.value %d\n", wpStatus.PluginUpdateCount)
    fmt.Printf("theme_update_count.value %d\n", wpStatus.ThemeUpdateCount)
    fmt.Printf("core_update_available.value %t\n", wpStatus.CoreUpdateAvailable)
    fmt.Printf("unapproved_comments.value %d\n", wpStatus.UnapprovedComments)
    fmt.Printf("response_time_ms.value %.2f\n", wpStatus.ResponseTimeMs)
    fmt.Printf("peak_script_memory_mb.value %.2f\n", wpStatus.PeakScriptMemoryMb)
    fmt.Printf("db_query_count.value %d\n", wpStatus.DBQueryCount)
}