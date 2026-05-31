//
//  WeekDaySelectorView.swift
//  imsitID
//

import SwiftUI

struct WeekDaySelectorView: View {
    @EnvironmentObject var appState: AppState
    
    let dayNames = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб"]
    let fullDayNames = ["Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота"]
    
    var body: some View {
        VStack(spacing: 16) {
            // Week selector with LiquidGlass
            HStack(spacing: 8) {
                WeekButton(week: 1, isSelected: appState.currentWeek == 1) {
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                        appState.switchWeek(1)
                    }
                }
                WeekButton(week: 2, isSelected: appState.currentWeek == 2) {
                    withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                        appState.switchWeek(2)
                    }
                }
            }
            
            // Day selector - centered with scrollable support
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(1...6, id: \.self) { day in
                        DayButton(
                            day: day,
                            name: dayNames[day - 1],
                            isSelected: appState.currentDay == day
                        ) {
                            withAnimation(.spring(response: 0.3, dampingFraction: 0.7)) {
                                appState.switchDay(day)
                            }
                        }
                    }
                }
                .padding(.horizontal, 4)
            }
        }
    }
}

struct WeekButton: View {
    let week: Int
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            Text("\(week) неделя")
                .font(.system(size: 15, weight: .semibold))
                .foregroundColor(isSelected ? .white : .white.opacity(0.8))
                .frame(maxWidth: .infinity)
                .padding(.vertical, 12)
                .background(
                    Group {
                        if isSelected {
                            LinearGradient(
                                colors: [
                                    Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.4),
                                    Color(red: 0.376, green: 0.510, blue: 0.980).opacity(0.3)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        } else {
                            Color.white.opacity(0.05)
                        }
                    }
                )
                .cornerRadius(14)
                .overlay(
                    RoundedRectangle(cornerRadius: 14)
                        .stroke(
                            isSelected ? 
                                LinearGradient(
                                    colors: [
                                        Color.white.opacity(0.4),
                                        Color.white.opacity(0.1)
                                    ],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                ) :
                                LinearGradient(
                                    colors: [
                                        Color.white.opacity(0.15),
                                        Color.white.opacity(0.05)
                                    ],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                ),
                            lineWidth: 1.5
                        )
                )
                .shadow(color: isSelected ? Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.3) : .clear, radius: 8, x: 0, y: 4)
        }
        .buttonStyle(LiquidGlassButtonStyle())
    }
}

struct DayButton: View {
    let day: Int
    let name: String
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            Text(name)
                .font(.system(size: 15, weight: .semibold))
                .foregroundColor(isSelected ? .white : .white.opacity(0.8))
                .frame(minWidth: 50)
                .padding(.horizontal, 18)
                .padding(.vertical, 12)
                .background(
                    Group {
                        if isSelected {
                            LinearGradient(
                                colors: [
                                    Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.4),
                                    Color(red: 0.376, green: 0.510, blue: 0.980).opacity(0.3)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        } else {
                            Color.white.opacity(0.05)
                        }
                    }
                )
                .cornerRadius(14)
                .overlay(
                    RoundedRectangle(cornerRadius: 14)
                        .stroke(
                            isSelected ? 
                                LinearGradient(
                                    colors: [
                                        Color.white.opacity(0.4),
                                        Color.white.opacity(0.1)
                                    ],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                ) :
                                LinearGradient(
                                    colors: [
                                        Color.white.opacity(0.15),
                                        Color.white.opacity(0.05)
                                    ],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                ),
                            lineWidth: 1.5
                        )
                )
                .shadow(color: isSelected ? Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.3) : .clear, radius: 8, x: 0, y: 4)
        }
        .buttonStyle(LiquidGlassButtonStyle())
        .scaleEffect(isSelected ? 1.05 : 1.0)
        .animation(.spring(response: 0.3, dampingFraction: 0.7), value: isSelected)
    }
}

// MARK: - LiquidGlass Button Style (iOS 26)
struct LiquidGlassButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.95 : 1.0)
            .opacity(configuration.isPressed ? 0.8 : 1.0)
            .animation(.spring(response: 0.2, dampingFraction: 0.6), value: configuration.isPressed)
    }
}

