//
//  imsitIDApp.swift
//  imsitID
//
//  Created on iOS
//

import SwiftUI

@main
struct imsitIDApp: App {
    @StateObject private var appState = AppState()
    
    var body: some Scene {
        WindowGroup {
            ContentView()
                .environmentObject(appState)
                .preferredColorScheme(.dark)
        }
    }
}

