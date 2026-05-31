//
//  SelectionPromptView.swift
//  imsitID
//

import SwiftUI

struct SelectionPromptView: View {
    let onSelectGroup: () -> Void
    let onSelectTeacher: () -> Void
    
    var body: some View {
        VStack(spacing: 16) {
            Text("Выберите группу или преподавателя")
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(.white)
                .padding(.top, 32)
            
            VStack(spacing: 12) {
                Button(action: onSelectGroup) {
                    HStack {
                        Image(systemName: "person.3.fill")
                            .font(.system(size: 18))
                        Text("Выбрать группу")
                            .font(.system(size: 16, weight: .medium))
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .glassEffect(cornerRadius: 12)
                    .foregroundColor(.white)
                }
                
                Button(action: onSelectTeacher) {
                    HStack {
                        Image(systemName: "person.fill")
                            .font(.system(size: 18))
                        Text("Выбрать преподавателя")
                            .font(.system(size: 16, weight: .medium))
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                    .background(
                        LinearGradient(
                            colors: [Color(red: 0.231, green: 0.510, blue: 0.965), Color(red: 0.118, green: 0.251, blue: 0.686)],
                            startPoint: .topLeading,
                            endPoint: .bottomTrailing
                        )
                    )
                    .cornerRadius(12)
                    .foregroundColor(.white)
                }
            }
            .padding(.horizontal, 16)
        }
    }
}

