//
//  GlassModifier.swift
//  imsitID
//

import SwiftUI

struct GlassModifier: ViewModifier {
    let cornerRadius: CGFloat
    let opacity: Double
    
    init(cornerRadius: CGFloat = 16, opacity: Double = 0.1) {
        self.cornerRadius = cornerRadius
        self.opacity = opacity
    }
    
    func body(content: Content) -> some View {
        content
            .background(
                RoundedRectangle(cornerRadius: cornerRadius)
                    .fill(.ultraThinMaterial)
                    .overlay(
                        RoundedRectangle(cornerRadius: cornerRadius)
                            .stroke(
                                LinearGradient(
                                    colors: [
                                        .white.opacity(0.2),
                                        .white.opacity(0.05)
                                    ],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                ),
                                lineWidth: 1
                            )
                    )
                    .shadow(color: .black.opacity(0.1), radius: 10, x: 0, y: 5)
            )
    }
}

extension View {
    func glassEffect(cornerRadius: CGFloat = 16, opacity: Double = 0.1) -> some View {
        modifier(GlassModifier(cornerRadius: cornerRadius, opacity: opacity))
    }
}

